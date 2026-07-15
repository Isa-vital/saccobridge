<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class MemberController extends Controller
{
    public function __construct(private readonly MemberService $members)
    {
    }

    /** Searchable member register (FR-MEM-08). */
    public function index(Request $request): Response
    {
        $members = Member::query()
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Member $member) => [
                'id' => $member->id,
                'member_no' => $member->member_no,
                'full_name' => $member->full_name,
                'type' => $member->type,
                'phone' => $member->phone,
                'nin' => $member->nin,
                'status' => $member->status,
                'joined_at' => $member->joined_at?->format('Y-m-d'),
            ]);

        return Inertia::render('members/Index', [
            'members' => $members,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('members/Create');
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['next_of_kin', 'photo', 'signature']);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('members/photos');
        }
        if ($request->hasFile('signature')) {
            $data['signature_path'] = $request->file('signature')->store('members/signatures');
        }

        $member = $this->members->register(
            $data,
            $request->validated('next_of_kin', []),
            $request->user(),
        );

        return redirect()->route('members.show', $member)
            ->with('success', "Member {$member->member_no} registered and awaiting approval.");
    }

    public function show(Member $member): Response
    {
        $member->load(['nextOfKin', 'documents.uploader', 'approver', 'creator']);

        return Inertia::render('members/Show', [
            'member' => [
                'id' => $member->id,
                'member_no' => $member->member_no,
                'type' => $member->type,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'full_name' => $member->full_name,
                'nin' => $member->nin,
                'date_of_birth' => $member->date_of_birth?->format('Y-m-d'),
                'gender' => $member->gender,
                'phone' => $member->phone,
                'email' => $member->email,
                'district' => $member->district,
                'subcounty' => $member->subcounty,
                'village' => $member->village,
                'occupation' => $member->occupation,
                'status' => $member->status,
                'joined_at' => $member->joined_at?->format('Y-m-d'),
                'approved_at' => $member->approved_at?->format('Y-m-d H:i'),
                'approver' => $member->approver?->name,
                'creator' => $member->creator?->name,
                'created_by' => $member->created_by,
                'next_of_kin' => $member->nextOfKin,
                'documents' => $member->documents->map(fn ($doc) => [
                    'id' => $doc->id,
                    'type' => $doc->type,
                    'original_name' => $doc->original_name,
                    'uploaded_by' => $doc->uploader?->name,
                    'created_at' => $doc->created_at->format('Y-m-d'),
                ]),
            ],
            'can' => [
                'update' => request()->user()->can('members.update'),
                'approve' => request()->user()->can('members.approve'),
            ],
        ]);
    }

    public function edit(Member $member): Response
    {
        $member->load('nextOfKin');

        return Inertia::render('members/Edit', [
            'member' => $member,
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $data = $request->safe()->except(['next_of_kin', 'photo', 'signature']);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('members/photos');
        }
        if ($request->hasFile('signature')) {
            $data['signature_path'] = $request->file('signature')->store('members/signatures');
        }

        $member->update($data);

        $member->nextOfKin()->delete();
        foreach ($request->validated('next_of_kin', []) as $kin) {
            $member->nextOfKin()->create($kin);
        }

        return redirect()->route('members.show', $member)->with('success', 'Member updated.');
    }

    /** Maker-checker approval (FR-MEM-05). */
    public function approve(Request $request, Member $member): RedirectResponse
    {
        abort_unless($request->user()->can('members.approve'), 403);

        try {
            $this->members->approve($member, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Member {$member->member_no} is now active.");
    }
}
