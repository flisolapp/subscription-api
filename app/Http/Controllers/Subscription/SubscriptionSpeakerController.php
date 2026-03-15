<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreSpeakerRequest;
use App\Models\People;
use App\Models\SpeakerTalk;
use App\Models\Talk;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SubscriptionSpeakerController extends Controller
{
    /**
     * POST /subscriptions/speakers
     *
     * Creates one People record per speaker and one Talk record per talk,
     * then links every speaker to every talk via the SpeakerTalk pivot —
     * all inside a single transaction.
     *
     * Expected payload (multipart/form-data):
     *   edition_id
     *   speakers[0][name], speakers[0][email], speakers[0][federal_code],
     *   speakers[0][phone], speakers[0][photo], speakers[0][bio], speakers[0][site]
     *   speakers[1][name], …  (repeat for each co-speaker)
     *   talks[0][title], talks[0][description], talks[0][shift], talks[0][kind],
     *   talks[0][talk_subject_id], talks[0][slide_file], talks[0][slide_url]
     *   talks[1][title], …    (repeat for each talk)
     *
     * S3 paths:
     *   Photo : people/{people_id}/photos/{uuidv7}.{ext}
     *   Slide : talks/{talk_id}/{uuidv7}.{ext}
     *
     * The id returned in the response is the first Talk id.
     */
    public function store(StoreSpeakerRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $speakersData = $request->input('speakers', []);
            $talksData    = $request->input('talks', []);

            // Laravel splits uploaded files from input(); retrieve them separately.
            $speakerFiles = $request->file('speakers', []);
            $talkFiles    = $request->file('talks', []);

            // ── 1. Create all People records ──────────────────────────────────
            $peopleIds = [];

            foreach ($speakersData as $i => $s) {
                $people = People::create([
                    'name'         => $s['name'],
                    'email'        => $s['email'],
                    'federal_code' => $s['federal_code'],
                    'phone'        => $s['phone'],
                    'bio'          => $s['bio']  ?? null,
                    'site'         => $s['site'] ?? null,
                    'use_free'     => false,
                ]);

                // Upload photo to S3 if present
                $photo = $speakerFiles[$i]['photo'] ?? null;
                if ($photo) {
                    $ext  = $photo->extension();
                    $path = "people/{$people->id}/photos/" . Str::uuid7() . ".{$ext}";
                    Storage::disk('s3')->put($path, file_get_contents($photo->getRealPath()));
                    $people->update(['photo' => $path]);
                }

                $peopleIds[] = $people->id;
            }

            // ── 2. Create all Talk records ────────────────────────────────────
            $talkIds = [];

            foreach ($talksData as $i => $t) {
                $talk = Talk::create([
                    'edition_id'      => $request->input('edition_id'),
                    'title'           => $t['title'],
                    'description'     => $t['description'],
                    'shift'           => $t['shift'],
                    'kind'            => $t['kind'],
                    'talk_subject_id' => $t['talk_subject_id'] ?? null,
                    'slide_url'       => $t['slide_url'] ?? null,
                    // slide_file resolved below after we have the talk ID
                ]);

                // Upload slide to S3 if present
                $slide = $talkFiles[$i]['slide_file'] ?? null;
                if ($slide) {
                    $ext  = $slide->extension();
                    $path = "talks/{$talk->id}/" . Str::uuid7() . ".{$ext}";
                    Storage::disk('s3')->put($path, file_get_contents($slide->getRealPath()));
                    $talk->update(['slide_file' => $path]);
                }

                $talkIds[] = $talk->id;
            }

            // ── 3. Link every speaker to every talk via the pivot ─────────────
            foreach ($peopleIds as $personId) {
                foreach ($talkIds as $talkId) {
                    SpeakerTalk::create([
                        'speaker_id' => $personId,
                        'talk_id'    => $talkId,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Inscrição realizada com sucesso.',
                'data'    => [
//                    'id'         => null,
                    'type'       => 'speaker',
                    'status'     => 'pending',
                    'created_at' => now()->toISOString(),
                    'speakers'   => collect($peopleIds)->map(fn ($id) => ['id' => $id])->values(),
                    'talks'      => collect($talkIds)->map(fn ($id) => ['id' => $id])->values(),
                ],
            ], 201);

        } catch (Throwable $e) {
            DB::rollBack();

            report($e);

            return response()->json([
                'message' => 'Erro interno ao processar a inscrição. Tente novamente.',
            ], 500);
        }
    }
}
