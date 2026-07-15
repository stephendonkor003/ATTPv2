<?php

namespace Database\Seeders;

use App\Models\DiscussionParticipant;
use App\Models\DiscussionPost;
use App\Models\DiscussionTheme;
use App\Models\DiscussionTopic;
use App\Models\DiscussionTopicDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DiscussionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $themes = collect([
                [
                    'name' => 'Policy & Governance',
                    'slug' => 'policy-governance',
                    'description' => 'Public policy, institutions, accountability, and evidence-informed governance across Africa.',
                    'icon' => 'shield',
                    'color' => '#006B3F',
                    'display_order' => 10,
                ],
                [
                    'name' => 'Trade & Investment',
                    'slug' => 'trade-investment',
                    'description' => 'Regional trade, AfCFTA implementation, investment, and inclusive economic transformation.',
                    'icon' => 'trending-up',
                    'color' => '#C69214',
                    'display_order' => 20,
                ],
                [
                    'name' => 'Research & Innovation',
                    'slug' => 'research-innovation',
                    'description' => 'Research practice, data, emerging technology, and innovation for African development.',
                    'icon' => 'cpu',
                    'color' => '#2563EB',
                    'display_order' => 30,
                ],
                [
                    'name' => 'Regional Collaboration',
                    'slug' => 'regional-collaboration',
                    'description' => 'Partnerships between think tanks, member states, regional institutions, and communities.',
                    'icon' => 'users',
                    'color' => '#7C3AED',
                    'display_order' => 40,
                ],
                [
                    'name' => 'Sustainable Development',
                    'slug' => 'sustainable-development',
                    'description' => 'Climate resilience, just transitions, health, food systems, and the Agenda 2063 goals.',
                    'icon' => 'globe',
                    'color' => '#0F766E',
                    'display_order' => 50,
                ],
            ])->mapWithKeys(function (array $attributes): array {
                $theme = DiscussionTheme::query()->updateOrCreate(
                    ['slug' => $attributes['slug']],
                    [...$attributes, 'is_active' => true]
                );

                return [$theme->slug => $theme];
            });

            $topics = [
                [
                    'theme' => 'trade-investment',
                    'title' => '90-day border clearance challenge: making AfCFTA work for women-led SMEs',
                    'slug' => '90-day-border-clearance-challenge-women-led-smes',
                    'summary' => 'Design one measurable operational change that can reduce time, cost, or uncertainty for women-led small businesses at an African border.',
                    'body' => <<<'TEXT'
Main discussion question

If a border agency could test one change within 90 days, what should it change to make cross-border trade faster and more predictable for women-led small businesses?

Please structure your contribution around:
1. Place and problem - name the country, corridor, or border post and describe the bottleneck.
2. Proposed change - identify one practical rule, process, information, or coordination improvement.
3. Accountable actors - state which public agency, private operator, or regional institution should lead.
4. Evidence of progress - suggest a baseline and one indicator, such as clearance time, number of steps, cost, or repeat journeys.
5. Inclusion safeguard - explain how the pilot will reach small and informal traders without creating a new digital, language, safety, or accessibility barrier.

Use operational experience or evidence where possible, but do not share personal or commercially sensitive information.
TEXT,
                    'is_featured' => true,
                    'related_links' => [
                        [
                            'title' => 'African Union - African Continental Free Trade Area',
                            'url' => 'https://au.int/en/african-continental-free-trade-area',
                            'description' => 'Official African Union information on the AfCFTA and its implementation.',
                            'type' => 'official-source',
                        ],
                        [
                            'title' => 'WTO Trade Facilitation Agreement Facility',
                            'url' => 'https://www.tfafacility.org/',
                            'description' => 'Implementation resources and good practice for faster, more transparent border procedures.',
                            'type' => 'official-source',
                        ],
                    ],
                    'materials' => [
                        [
                            'title' => 'Read the 90-day border pilot brief',
                            'url' => '/assets/discussion-documents/afcfta-border-clearance-pilot-brief.html',
                            'description' => 'A one-page scenario, response framework, measurement table, and facilitation questions for this discussion.',
                            'type' => 'discussion-brief',
                        ],
                        [
                            'title' => 'World Bank Logistics Performance Index',
                            'url' => 'https://lpi.worldbank.org/',
                            'description' => 'Comparable logistics indicators that can help participants frame measurable bottlenecks and outcomes.',
                            'type' => 'data-source',
                        ],
                    ],
                    'documents' => [],
                ],
                [
                    'theme' => 'policy-governance',
                    'title' => 'Welcome to the ATTP Public Discussion Forum',
                    'slug' => 'welcome-to-the-attp-public-discussion-forum',
                    'summary' => 'Introduce yourself and share the policy questions that should shape this Pan-African community.',
                    'body' => 'This forum is a space for respectful, evidence-informed exchange among researchers, policy practitioners, institutions, and citizens. Tell the community what you work on and which policy questions deserve collective attention.',
                    'is_featured' => false,
                    'related_links' => [
                        [
                            'title' => 'African Union',
                            'url' => 'https://au.int/',
                            'description' => 'Official African Union website and institutional information.',
                            'type' => 'link',
                        ],
                    ],
                    'materials' => [
                        [
                            'title' => 'Agenda 2063 Overview',
                            'url' => 'https://au.int/en/agenda2063/overview',
                            'description' => 'African Union overview of the continent’s long-term development framework.',
                            'type' => 'guidance',
                        ],
                    ],
                    'documents' => [
                        [
                            'title' => 'ATTP Community Discussion Guide',
                            'url' => '/assets/attp-community-discussion-guide.txt',
                            'description' => 'A short plain-text guide to respectful, evidence-informed participation.',
                            'type' => 'guide',
                        ],
                    ],
                ],
                [
                    'theme' => 'trade-investment',
                    'title' => 'Which research gaps are slowing inclusive AfCFTA implementation?',
                    'slug' => 'research-gaps-inclusive-afcfta-implementation',
                    'summary' => 'Identify the evidence, data, and policy coordination gaps that need urgent attention.',
                    'body' => 'Share concrete research gaps, examples from your country or region, and practical ways that think tanks can collaborate to support inclusive AfCFTA implementation.',
                    'is_featured' => false,
                    'related_links' => [
                        [
                            'title' => 'African Union — African Continental Free Trade Area',
                            'url' => 'https://au.int/en/african-continental-free-trade-area',
                            'description' => 'Official African Union information about the AfCFTA.',
                            'type' => 'link',
                        ],
                    ],
                    'materials' => [
                        [
                            'title' => 'United Nations Economic Commission for Africa',
                            'url' => 'https://www.uneca.org/',
                            'description' => 'Regional economic research, data, and policy resources for Africa.',
                            'type' => 'website',
                        ],
                    ],
                    'documents' => [],
                ],
                [
                    'theme' => 'research-innovation',
                    'title' => 'Responsible use of AI in African policy research',
                    'slug' => 'responsible-ai-in-african-policy-research',
                    'summary' => 'Discuss safeguards, opportunities, and shared standards for using AI in policy research.',
                    'body' => 'How should African research institutions use AI while protecting research integrity, local knowledge, privacy, and public trust? Contribute principles, examples, and resources.',
                    'is_featured' => false,
                    'related_links' => [
                        [
                            'title' => 'UNESCO Artificial Intelligence',
                            'url' => 'https://www.unesco.org/en/artificial-intelligence',
                            'description' => 'UNESCO’s official artificial-intelligence programme pages.',
                            'type' => 'link',
                        ],
                    ],
                    'materials' => [
                        [
                            'title' => 'Recommendation on the Ethics of Artificial Intelligence',
                            'url' => 'https://www.unesco.org/en/artificial-intelligence/recommendation-ethics',
                            'description' => 'UNESCO’s official ethical framework and implementation resources.',
                            'type' => 'guidance',
                        ],
                    ],
                    'documents' => [],
                ],
            ];

            foreach ($topics as $topic) {
                DiscussionTopic::query()->updateOrCreate(
                    ['slug' => $topic['slug']],
                    [
                        'theme_id' => $themes[$topic['theme']]->id,
                        'created_by' => null,
                        'title' => $topic['title'],
                        'summary' => $topic['summary'],
                        'body' => $topic['body'],
                        'related_links' => $topic['related_links'] ?? [],
                        'materials' => $topic['materials'] ?? [],
                        'documents' => $topic['documents'] ?? [],
                        'status' => 'open',
                        'is_featured' => $topic['is_featured'],
                        'requires_moderation' => false,
                        'allow_replies' => true,
                        'starts_at' => null,
                        'closes_at' => null,
                    ]
                );
            }

            $sampleTopic = DiscussionTopic::query()
                ->where('slug', '90-day-border-clearance-challenge-women-led-smes')
                ->firstOrFail();

            $communityModerator = DiscussionParticipant::query()->firstOrCreate(
                ['email' => 'community-moderator@discussion.attp.invalid'],
                [
                    'display_name' => 'ATTP Community Moderator',
                    'password' => Str::random(64),
                    'country' => null,
                    'organization' => 'ATTP Policy Community',
                    'bio' => 'Community host account used to introduce structured public policy discussions.',
                    'status' => 'active',
                    'terms_accepted_at' => now(),
                    'last_login_at' => null,
                    'last_seen_at' => null,
                ]
            );

            $communityModerator->forceFill([
                'display_name' => 'ATTP Community Moderator',
                'organization' => 'ATTP Policy Community',
                'bio' => 'Community host account used to introduce structured public policy discussions.',
                'status' => 'active',
                'blocked_at' => null,
                'blocked_reason' => null,
            ])->save();

            DiscussionPost::query()->updateOrCreate(
                [
                    'topic_id' => $sampleTopic->id,
                    'participant_id' => $communityModerator->id,
                    'parent_id' => null,
                ],
                [
                    'body' => <<<'TEXT'
To get the conversation moving, share one border or corridor you know and make your recommendation testable.

A useful contribution can be brief: "At [border], [step] currently causes [delay or cost]. For a 90-day pilot, [lead agency] should [specific change]. We would track [indicator] from [baseline] to [target], and consult [affected trader group] to check that the change is inclusive."

You can also reply to another participant to strengthen their indicator, add evidence, or identify an implementation risk.
TEXT,
                    'status' => 'published',
                    'moderated_by' => null,
                    'moderated_at' => now(),
                    'moderation_reason' => 'Seeded community moderator prompt.',
                ]
            );
        });

        $this->seedSampleUploadedDocument();
    }

    private function seedSampleUploadedDocument(): void
    {
        $topic = DiscussionTopic::query()
            ->where('slug', '90-day-border-clearance-challenge-women-led-smes')
            ->firstOrFail();
        $sourcePath = public_path('assets/discussion-documents/afcfta-border-clearance-pilot-brief.html');
        $html = file_get_contents($sourcePath);

        if (! is_string($html) || $html === '') {
            throw new \RuntimeException('The sample discussion worksheet source is missing.');
        }

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $renderer = new Dompdf($options);
        $renderer->loadHtml($html, 'UTF-8');
        $renderer->setPaper('A4');
        $renderer->render();
        $pdf = $renderer->output();

        if (! is_string($pdf) || $pdf === '') {
            throw new \RuntimeException('The sample discussion PDF could not be generated.');
        }

        $storagePath = "discussion-documents/{$topic->id}/afcfta-border-clearance-pilot-brief.pdf";

        if (! Storage::disk('local')->put($storagePath, $pdf)) {
            throw new \RuntimeException('The sample discussion PDF could not be stored.');
        }

        try {
            DiscussionTopicDocument::query()->updateOrCreate(
                [
                    'topic_id' => $topic->id,
                    'storage_path' => $storagePath,
                ],
                [
                    'uploaded_by' => null,
                    'title' => 'Border clearance pilot brief and response worksheet',
                    'description' => 'A printable one-page brief and worksheet for preparing a measurable 90-day border improvement proposal.',
                    'type' => 'pdf',
                    'file_name' => 'afcfta-border-clearance-pilot-brief.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => strlen($pdf),
                    'display_order' => 10,
                ]
            );
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storagePath);
            throw $exception;
        }
    }
}
