<?php

use App\Mail\GrmAutoResponseMail;
use App\Mail\GrmReminderMail;
use App\Mail\GrmResponsibleOfficerMail;
use App\Models\GrmGrievance;
use App\Models\GrmLevel;
use App\Models\Permission;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\MasterAdminSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

(new PHPUnit\TextUI\Configuration\Builder)->build(['phpunit']);

class GrmPublicAnonymousSmoke
{
    use InteractsWithAuthentication;
    use InteractsWithSession;
    use MakesHttpRequests;

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function run(): void
    {
        $this->assertTrue(
            Schema::hasColumns('grm_grievances', ['anonymous_contact_method', 'anonymous_contact_value']),
            'The confidential anonymous contact migration has not been run.'
        );

        $program = Program::query()->first();
        $this->assertTrue((bool) $program, 'A program is required for the public grievance smoke test.');

        Queue::fake();
        DB::beginTransaction();

        try {
            $publicFormResponse = $this->get(route('public.grievances.create'));
            $this->assertTrue(
                $publicFormResponse->getStatusCode() === 200,
                'The public grievance form did not load. Redirect: '.($publicFormResponse->headers->get('Location') ?: 'none')
            );
            $publicFormResponse
                ->assertSee('Speak up. We are listening.')
                ->assertSee('Tell us what happened')
                ->assertSee('Your privacy matters')
                ->assertSee('What happens next?')
                ->assertSee('Incident Details / Summary')
                ->assertSee('Public Website')
                ->assertSee('Automatically detected by the system.')
                ->assertSee('Your identity will be hidden')
                ->assertSee('form="grmSubmissionForm"', false)
                ->assertSee('document.body.appendChild(privacyModalElement);', false)
                ->assertDontSee('<select name="channel"', false)
                ->assertDontSee('name="level_id"', false)
                ->assertSee(route('public.grievances.store'));

            $craftedLevelSubject = 'Submitter level injection '.Str::uuid();
            $this->postWithCsrf(route('public.grievances.store'), [
                'program_id' => $program->id,
                'level_id' => (string) Str::uuid(),
                'subject' => $craftedLevelSubject,
                'description' => 'A complainant must not be able to classify a grievance through a crafted request.',
                'is_anonymous' => '0',
            ])->assertSessionHasErrors('level_id');
            $this->assertTrue(
                ! GrmGrievance::query()->where('subject', $craftedLevelSubject)->exists(),
                'A submitter assigned a grievance level through a crafted request.'
            );

            $this->postWithCsrf(route('public.grievances.store'), [
                'program_id' => $program->id,
                'subject' => 'Anonymous contact validation smoke',
                'description' => 'This submission must be rejected without a private reply contact.',
                'is_anonymous' => '1',
            ])->assertSessionHasErrors([
                'anonymous_contact_method',
                'anonymous_contact_value',
            ]);

            $subject = 'Anonymous public grievance '.Str::uuid();
            $privateEmail = 'private-'.Str::lower(Str::random(12)).'@example.test';

            $this->postWithCsrf(route('public.grievances.store'), [
                'program_id' => $program->id,
                'subject' => $subject,
                'description' => 'This is a valid anonymous public grievance used to verify confidential replies.',
                'is_anonymous' => '1',
                'anonymous_contact_method' => 'email',
                'anonymous_contact_value' => $privateEmail,
                'channel' => 'phone',
            ])->assertRedirect(route('public.grievances.create'));

            $grievance = GrmGrievance::query()->where('subject', $subject)->first();
            $this->assertTrue((bool) $grievance, 'The anonymous public grievance was not stored.');
            $this->assertTrue($grievance->is_anonymous, 'The public grievance was not marked anonymous.');
            $this->assertTrue($grievance->submitted_by === null, 'An anonymous grievance retained the signed-in user identity.');
            $this->assertTrue($grievance->submitter_name === null, 'An anonymous grievance retained the complainant name.');
            $this->assertTrue($grievance->submitter_email === null, 'An anonymous grievance retained the complainant email field.');
            $this->assertTrue($grievance->level_id === null, 'A new grievance was classified before officer review.');
            $this->assertTrue($grievance->replyEmail() === $privateEmail, 'The encrypted private reply email did not round-trip.');
            $this->assertTrue($grievance->channel === 'public_portal', 'The public grievance channel was not enforced.');

            $storedContact = DB::table('grm_grievances')
                ->where('id', $grievance->id)
                ->value('anonymous_contact_value');
            $this->assertTrue(
                filled($storedContact) && $storedContact !== $privateEmail,
                'The anonymous reply contact was not encrypted at rest.'
            );

            $this->assertBrandedEmails($grievance);
            $this->assertOfficerCanRecordOriginalChannel($program);

            echo "GRM_PUBLIC_ANONYMOUS_OK\n";
        } finally {
            DB::rollBack();
            $this->app['auth']->forgetGuards();
        }
    }

    private function assertBrandedEmails(GrmGrievance $grievance): void
    {
        $grievance->loadMissing(['program.sector', 'level', 'submitter']);
        $grievance->setAttribute('attachments_count', 0);
        $officer = User::query()->firstOrFail();
        $emails = [
            [(new GrmAutoResponseMail($grievance))->render(), 'Your grievance has been received'],
            [(new GrmResponsibleOfficerMail($grievance, $officer, route('grm.logs.show', $grievance)))->render(), 'A new grievance has been assigned'],
            [(new GrmReminderMail($grievance))->render(), 'This grievance requires attention'],
            [(new GrmReminderMail($grievance, null, 'escalation'))->render(), 'This grievance has reached escalation'],
        ];

        foreach ($emails as [$html, $heading]) {
            $this->assertTrue(str_contains($html, 'African Think Tank Platform'), "The {$heading} email is missing its branded header.");
            $this->assertTrue(str_contains($html, $heading), "The {$heading} email is missing its main body heading.");
            $this->assertTrue(str_contains($html, 'Confidential case communication'), "The {$heading} email is missing its confidentiality footer.");
            $this->assertTrue(str_contains($html, 'Developed, maintained and supported'), "The {$heading} email is missing its technical footer.");
        }
    }

    private function assertOfficerCanRecordOriginalChannel(Program $program): void
    {
        $officer = User::query()->where('email', MasterAdminSeeder::EMAIL)->firstOrFail();
        $officer->forceFill(['email_verified_at' => $officer->email_verified_at ?: now()])->save();
        $grmViewPermission = Permission::query()->where('name', 'grm.view')->firstOrFail();
        $officer->permissions()->syncWithoutDetaching([$grmViewPermission->id]);
        $officer->unsetRelation('permissions');
        $levelSuffix = Str::lower(Str::random(10));
        $level = GrmLevel::create([
            'name' => 'Officer classified '.$levelSuffix,
            'slug' => 'officer-classified-'.$levelSuffix,
            'color' => '#0f766e',
            'priority' => 1,
            'response_due_hours' => 24,
            'resolution_due_hours' => 72,
            'is_active' => true,
            'created_by' => $officer->id,
        ]);

        $this->actingAs($officer);
        $this->withSession([
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $officer->id,
            'otp_verified_at' => now()->toIso8601String(),
        ]);
        $officerFormResponse = $this->get(route('grm.submissions.create'));
        $this->assertTrue(
            $officerFormResponse->getStatusCode() === 200,
            'The grievance officer intake form did not load. Redirect: '.($officerFormResponse->headers->get('Location') ?: 'none')
        );
        $officerFormResponse
            ->assertSee('GRM intake officer access')
            ->assertSee('Channel / Received Through')
            ->assertSee('<select name="channel"', false)
            ->assertDontSee('name="level_id"', false);

        $subject = 'Officer on-behalf grievance '.Str::uuid();
        $this->postWithCsrf(route('grm.submissions.store'), [
            'program_id' => $program->id,
            'channel' => 'phone',
            'subject' => $subject,
            'description' => 'The GRM officer is recording these incident details on behalf of a caller.',
            'submitter_name' => 'Telephone complainant',
            'submitter_phone' => '+254700000001',
            'is_anonymous' => '0',
        ]);

        $grievance = GrmGrievance::query()->where('subject', $subject)->first();
        $this->assertTrue((bool) $grievance, 'The officer on-behalf grievance was not stored.');
        $this->assertTrue($grievance->level_id === null, 'The intake workflow classified the grievance before case review.');
        $this->assertTrue($grievance->channel === 'phone', 'The authorized GRM officer could not retain the original phone channel.');
        $this->assertTrue((string) $grievance->submitted_by === (string) $officer->id, 'The officer audit identity was not retained.');

        $caseResponse = $this->get(route('grm.logs.show', $grievance));
        $this->assertTrue(
            $caseResponse->getStatusCode() === 200,
            'The grievance officer could not open the secured case log. Redirect: '.($caseResponse->headers->get('Location') ?: 'none')
        );
        $caseResponse
            ->assertSee('Grievance Level / Category')
            ->assertSee($level->name);

        $this->postWithCsrf(route('grm.logs.status', $grievance), [
            'level_id' => $level->id,
            'status' => 'under_review',
            'assigned_to' => $officer->id,
            'notes' => 'Officer reviewed and classified the grievance.',
        ])->assertRedirect(route('grm.logs.show', $grievance));

        $grievance->refresh();
        $this->assertTrue((string) $grievance->level_id === (string) $level->id, 'The grievance officer could not classify the case.');
        $this->assertTrue(
            $grievance->events()->where('event_type', 'classified')->exists(),
            'The officer classification was not recorded in the case timeline.'
        );
    }

    private function postWithCsrf(string $uri, array $data = [])
    {
        $token = Str::random(40);

        return $this->withSession(['_token' => $token])
            ->post($uri, ['_token' => $token, ...$data]);
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}

(new GrmPublicAnonymousSmoke($app))->run();
