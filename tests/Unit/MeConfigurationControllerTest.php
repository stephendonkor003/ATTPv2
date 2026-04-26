<?php

namespace Tests\Unit;

use App\Http\Controllers\MeConfigurationController;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class MeConfigurationControllerTest extends TestCase
{
    public function test_it_preserves_existing_public_survey_settings_when_updating_the_title(): void
    {
        $controller = new MeConfigurationController();
        $request = Request::create('/methodologies/1', 'POST', [
            'survey_public_enabled' => '1',
            'survey_title' => 'post workshop survey',
            'survey_intro' => 'Updated welcome copy',
            'survey_estimated_minutes' => '15',
        ]);

        $existingMetadata = [
            'survey' => [
                'enabled' => true,
                'title' => 'POST WORKSHOP SURVEY',
                'intro' => 'Legacy intro copy',
                'presentation' => [
                    'show_side_navigation' => false,
                    'show_briefing_panel' => false,
                    'compact_title' => true,
                    'show_public_qr' => true,
                    'unified_typography' => true,
                ],
                'estimated_time_label' => '10-15 minutes',
                'respondent' => [
                    'show_notes' => false,
                    'fields' => [
                        'name' => [
                            'required' => true,
                            'label' => 'Participant name',
                            'placeholder' => 'Enter participant name',
                        ],
                    ],
                ],
                'sections' => [
                    [
                        'key' => 'session_feedback',
                        'title' => 'Session Feedback',
                        'questions' => [
                            [
                                'key' => 'delivery_rating',
                                'label' => 'How do you rate the session delivery?',
                                'type' => 'radio',
                                'required' => true,
                                'options' => ['Excellent', 'Good', 'Fair'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $method = new ReflectionMethod(MeConfigurationController::class, 'buildMethodologyMetadata');
        $method->setAccessible(true);

        $metadata = $method->invoke(
            $controller,
            'Workshop Survey',
            $request,
            $existingMetadata,
            $existingMetadata['survey']['sections']
        );

        $this->assertSame('post workshop survey', data_get($metadata, 'survey.title'));
        $this->assertSame('Updated welcome copy', data_get($metadata, 'survey.intro'));
        $this->assertSame(15, data_get($metadata, 'survey.estimated_minutes'));
        $this->assertFalse(data_get($metadata, 'survey.presentation.show_side_navigation'));
        $this->assertFalse(data_get($metadata, 'survey.presentation.show_briefing_panel'));
        $this->assertTrue(data_get($metadata, 'survey.presentation.compact_title'));
        $this->assertTrue(data_get($metadata, 'survey.presentation.show_public_qr'));
        $this->assertTrue(data_get($metadata, 'survey.presentation.unified_typography'));
        $this->assertSame('10-15 minutes', data_get($metadata, 'survey.estimated_time_label'));
        $this->assertFalse(data_get($metadata, 'survey.respondent.show_notes'));
        $this->assertTrue(data_get($metadata, 'survey.respondent.fields.name.required'));
        $this->assertSame('Participant name', data_get($metadata, 'survey.respondent.fields.name.label'));
        $this->assertSame('Enter participant name', data_get($metadata, 'survey.respondent.fields.name.placeholder'));
        $this->assertCount(1, data_get($metadata, 'survey.sections'));
        $this->assertCount(1, data_get($metadata, 'survey.questions'));
    }
}
