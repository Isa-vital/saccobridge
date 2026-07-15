<?php

namespace Tests\Feature\Members;

use App\Models\Member;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function officer(): User
    {
        return User::factory()->create()->assignRole('loan-officer');
    }

    private function manager(): User
    {
        return User::factory()->create()->assignRole('manager');
    }

    public function test_loan_officer_can_register_a_member(): void
    {
        $response = $this->actingAs($this->officer())->post('/members', [
            'type' => 'individual',
            'first_name' => 'Grace',
            'last_name' => 'Nakato',
            'nin' => 'CM123456789012',
            'date_of_birth' => '1990-05-04',
            'gender' => 'female',
            'phone' => '+256701234567',
            'next_of_kin' => [
                ['name' => 'John Nakato', 'relationship' => 'Spouse', 'phone' => '+256702000000'],
            ],
        ]);

        $member = Member::firstWhere('nin', 'CM123456789012');

        $response->assertRedirect("/members/{$member->id}");
        $this->assertSame('pending', $member->status);
        $this->assertSame('SB-00001', $member->member_no);
        $this->assertCount(1, $member->nextOfKin);
    }

    public function test_member_numbers_are_sequential(): void
    {
        $officer = $this->officer();

        foreach ([['Alice', '+256701111111'], ['Bob', '+256702222222']] as [$name, $phone]) {
            $this->actingAs($officer)->post('/members', [
                'type' => 'individual',
                'first_name' => $name,
                'last_name' => 'Test',
                'date_of_birth' => '1990-01-01',
                'phone' => $phone,
                'next_of_kin' => [],
            ]);
        }

        $this->assertSame(['SB-00001', 'SB-00002'], Member::orderBy('id')->pluck('member_no')->all());
    }

    public function test_maker_cannot_approve_their_own_registration(): void
    {
        $officer = User::factory()->create()->assignRole('manager'); // manager has both create+approve
        $member = Member::factory()->create(['created_by' => $officer->id]);

        $this->actingAs($officer)->post("/members/{$member->id}/approve");

        $this->assertSame('pending', $member->fresh()->status);
    }

    public function test_manager_can_approve_a_pending_member(): void
    {
        $member = Member::factory()->create(['created_by' => $this->officer()->id]);

        $this->actingAs($this->manager())->post("/members/{$member->id}/approve");

        $member->refresh();
        $this->assertSame('active', $member->status);
        $this->assertNotNull($member->approved_at);
        $this->assertNotNull($member->joined_at);
    }

    public function test_teller_cannot_register_members(): void
    {
        $teller = User::factory()->create()->assignRole('teller');

        $this->actingAs($teller)->post('/members', [
            'type' => 'individual',
            'first_name' => 'Blocked',
            'phone' => '+256700000000',
        ])->assertForbidden();
    }

    public function test_underage_members_are_rejected(): void
    {
        $this->actingAs($this->officer())->post('/members', [
            'type' => 'individual',
            'first_name' => 'Young',
            'last_name' => 'Person',
            'date_of_birth' => now()->subYears(16)->format('Y-m-d'),
            'phone' => '+256703333333',
            'next_of_kin' => [],
        ])->assertSessionHasErrors('date_of_birth');
    }

    public function test_member_register_is_searchable(): void
    {
        Member::factory()->active()->create(['first_name' => 'Sarah', 'last_name' => 'Auma', 'phone' => '+256705555555']);
        Member::factory()->active()->create(['first_name' => 'Peter', 'last_name' => 'Okello', 'phone' => '+256706666666']);

        $response = $this->actingAs($this->officer())->get('/members?search=Auma');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('members/Index')
            ->has('members.data', 1)
            ->where('members.data.0.full_name', 'Sarah Auma'));
    }
}
