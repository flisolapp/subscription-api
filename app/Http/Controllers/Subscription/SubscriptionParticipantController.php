<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreParticipantRequest;
use App\Models\Participant;
use App\Models\People;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SubscriptionParticipantController extends Controller
{
    /**
     * POST /subscriptions/participants
     *
     * Creates a People record and links it to a new Participant for the
     * given edition. A photo file, if provided, is stored on S3 at:
     *   people/{people_id}/photos/{uuidv7}.{ext}
     *
     * Each submission always creates a new People + Participant pair.
     * Deduplication / sanitisation is handled in a later iteration.
     */
    public function store(StoreParticipantRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // ── 1. Create People ──────────────────────────────────────────────
            $people = People::create([
                'name'            => $request->name,
                'email'           => $request->email,
                'federal_code'    => $request->federal_code,
                'phone'           => $request->phone,
                'bio'             => $request->bio,
                'site'            => $request->site,
                'use_free'        => $request->boolean('use_free', false),
                'distro_id'       => $request->distro_id,
                'student_info_id' => $request->student_info_id,
                'student_place'   => $request->student_place,
                'student_course'  => $request->student_course,
                'address_state'   => $request->address_state,
                // photo resolved below after we have the people ID
            ]);

            // ── 2. Upload photo to S3 (if present) ───────────────────────────
            if ($request->hasFile('photo')) {
                $photo = $request->file('photo');
                $ext   = $photo->extension();
                $path  = "people/{$people->id}/photos/" . Str::uuid7() . ".{$ext}";

                Storage::disk('s3')->put($path, file_get_contents($photo->getRealPath()));

                $people->update(['photo' => $path]);
            }

            // ── 3. Create Participant ─────────────────────────────────────────
            $participant = Participant::create([
                'edition_id' => $request->edition_id,
                'people_id'  => $people->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Inscrição realizada com sucesso.',
                'data'    => [
                    'id'         => $participant->id,
                    'type'       => 'participant',
                    'status'     => 'pending',
                    'created_at' => $participant->created_at->toISOString(),
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
