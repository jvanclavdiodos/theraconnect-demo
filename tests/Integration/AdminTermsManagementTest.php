<?php

namespace Tests\Integration;

use App\Models\LegalDocument;
use App\Models\User;
use App\Support\TermsOfService;
use Tests\TestCase;

class AdminTermsManagementTest extends TestCase
{
    public function test_only_admin_sees_and_can_update_the_agreement_from_account(): void
    {
        $admin = $this->createAdmin();
        $clinician = $this->createClinician();

        $this->actingAs($admin)
            ->get(route('account.edit'))
            ->assertOk()
            ->assertSee('User agreement and privacy notice')
            ->assertSee('Publish agreement');

        $this->actingAs($clinician['user'])
            ->get(route('account.edit'))
            ->assertOk()
            ->assertDontSee('Publish agreement');

        $this->actingAs($clinician['user'])
            ->put(route('account.terms.update'), [
                'terms_content' => $this->agreementContent('Unauthorized'),
                'current_password' => 'password',
            ])
            ->assertForbidden();
    }

    public function test_admin_password_is_required_and_update_is_audited(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->from(route('account.edit'))
            ->put(route('account.terms.update'), [
                'terms_content' => $this->agreementContent('Updated agreement'),
                'current_password' => 'wrong-password',
            ])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHasErrors('current_password');

        $this->assertDatabaseCount('legal_documents', 0);

        $this->actingAs($admin)
            ->put(route('account.terms.update'), [
                'terms_content' => $this->agreementContent('Updated agreement'),
                'current_password' => 'password',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $document = LegalDocument::where('key', TermsOfService::DOCUMENT_KEY)->firstOrFail();
        $this->assertSame($admin->id, $document->updated_by);
        $this->assertStringContainsString('Updated agreement', $document->content);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'user_agreement.updated',
            'target_id' => $document->id,
        ]);
    }

    public function test_published_agreement_is_safely_rendered_and_used_for_new_acceptances(): void
    {
        $admin = $this->createAdmin();
        $content = $this->agreementContent('Published wording <script>alert(1)</script>');

        $this->actingAs($admin)->put(route('account.terms.update'), [
            'terms_content' => $content,
            'current_password' => 'password',
        ])->assertRedirect();

        $document = LegalDocument::firstOrFail();
        auth()->logout();

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Published wording')
            ->assertDontSee('Published wording <script>', false);

        $this->getJson('/api/v1/legal/terms')
            ->assertOk()
            ->assertJsonPath('data.version', $document->version)
            ->assertJsonPath('data.content', fn ($value) => str_contains($value, 'Published wording'));

        $this->post(route('register'), [
            'name' => 'Terms Patient',
            'email' => 'terms-patient@test.com',
            'password' => 'ValidPass1!',
            'password_confirmation' => 'ValidPass1!',
            'accepted_terms' => '1',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertSame(
            $document->version,
            User::where('email', 'terms-patient@test.com')->value('terms_version')
        );
    }

    private function agreementContent(string $marker): string
    {
        return "**Effective date: August 25, 2026**\n\n###### Agreement\n\n{$marker}\n\n".
            str_repeat('This agreement explains account use, privacy, care coordination, and user responsibilities. ', 4);
    }
}
