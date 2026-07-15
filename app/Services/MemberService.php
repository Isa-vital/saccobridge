<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Member lifecycle: register (pending) → approve (active) → dormant/exited.
 * Framework-agnostic core logic per docs/02-architecture.md.
 */
class MemberService
{
    /**
     * Register a new member in `pending` status (FR-MEM-01..05).
     *
     * @param array $data validated member attributes
     * @param array<int, array> $nextOfKin list of next-of-kin rows (FR-MEM-02)
     */
    public function register(array $data, array $nextOfKin, User $createdBy): Member
    {
        return DB::transaction(function () use ($data, $nextOfKin, $createdBy) {
            $member = Member::create([
                ...$data,
                'member_no' => $this->nextMemberNo(),
                'status' => 'pending',
                'created_by' => $createdBy->id,
            ]);

            foreach ($nextOfKin as $kin) {
                $member->nextOfKin()->create($kin);
            }

            return $member;
        });
    }

    /**
     * Approve (activate) a pending member — maker-checker (FR-MEM-05).
     * The approver must be a different user from the creator.
     *
     * TODO Phase 3: post membership fee to GL on activation (FR-MEM-06).
     */
    public function approve(Member $member, User $approver): Member
    {
        if ($member->status !== 'pending') {
            throw new InvalidArgumentException('Only pending members can be approved.');
        }

        if ($member->created_by !== null && $member->created_by === $approver->id) {
            throw new InvalidArgumentException('A member cannot be approved by the same user who registered them (maker-checker).');
        }

        $member->update([
            'status' => 'active',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'joined_at' => today(),
        ]);

        return $member;
    }

    /**
     * Exit a member (FR-MEM-07).
     *
     * TODO Phase 4/6: block exit while loans are outstanding and settle
     * savings/share balances once those modules exist.
     */
    public function exit(Member $member): Member
    {
        if ($member->status === 'exited') {
            throw new InvalidArgumentException('Member has already exited.');
        }

        $member->update([
            'status' => 'exited',
            'exited_at' => today(),
        ]);

        return $member;
    }

    /** Store an uploaded KYC document on the tenant disk (FR-MEM-03). */
    public function storeDocument(Member $member, UploadedFile $file, string $type, User $uploadedBy): void
    {
        $path = $file->store("members/{$member->id}/documents");

        $member->documents()->create([
            'type' => $type,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $uploadedBy->id,
        ]);
    }

    /**
     * Generate the next sequential member number, e.g. SB-00001 (FR-MEM-04).
     * Uses a settings row locked FOR UPDATE so numbers are gapless and unique
     * under concurrency. Must be called inside a transaction.
     */
    protected function nextMemberNo(): string
    {
        $prefix = Setting::get('member_no_prefix', 'SB');

        $row = DB::table('settings')->where('key', 'member_no_sequence')->lockForUpdate()->first();

        if ($row === null) {
            DB::table('settings')->insert([
                'key' => 'member_no_sequence',
                'value' => json_encode(1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sequence = 1;
        } else {
            $sequence = (int) json_decode($row->value) + 1;
            DB::table('settings')->where('key', 'member_no_sequence')
                ->update(['value' => json_encode($sequence), 'updated_at' => now()]);
        }

        return sprintf('%s-%05d', $prefix, $sequence);
    }
}
