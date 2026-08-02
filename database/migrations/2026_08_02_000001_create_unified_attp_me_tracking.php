<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_disaggregation_dimensions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->string('dimension_group', 40)->default('classification');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('me_disaggregation_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('dimension_id')->constrained('me_disaggregation_dimensions')->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('code', 100);
            $table->string('name', 180);
            $table->json('metadata')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['dimension_id', 'code'], 'me_disagg_options_dimension_code_unique');
            $table->index(['dimension_id', 'is_active', 'sort_order'], 'me_disagg_options_active_idx');
        });
        Schema::table('me_disaggregation_options', function (Blueprint $table): void {
            $table->foreign('parent_id', 'me_disaggregation_options_parent_id_foreign')
                ->references('id')
                ->on('me_disaggregation_options')
                ->nullOnDelete();
        });

        Schema::create('me_indicator_disaggregation_requirements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('indicator_id')->constrained('myb_indicators')->cascadeOnDelete();
            $table->foreignUuid('dimension_id')->constrained('me_disaggregation_dimensions')->restrictOnDelete();
            $table->boolean('is_required')->default(false);
            $table->boolean('collect_numeric_value')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['indicator_id', 'dimension_id'], 'me_indicator_disagg_requirement_unique');
        });

        Schema::create('me_focal_unit_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('consortium_name', 120);
            $table->foreignUuid('think_tank_member_id')->nullable()->constrained('attp_consortium_think_tanks')->nullOnDelete();
            $table->string('think_tank_label', 160);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('focal_person_name', 180);
            $table->string('email')->unique();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->string('source', 80)->default('ATTP unified tracker');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['think_tank_member_id', 'is_active'], 'me_focal_contact_tank_active_idx');
        });

        Schema::table('myb_indicators', function (Blueprint $table): void {
            $table->string('organization_rollup_method', 40)->default('sum')->after('aggregation_method');
            $table->foreignUuid('code_updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->index('organization_rollup_method', 'me_indicators_org_rollup_idx');
        });

        Schema::create('me_indicator_code_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('indicator_id')->constrained('myb_indicators')->cascadeOnDelete();
            $table->string('old_code')->nullable();
            $table->string('new_code');
            $table->text('change_reason')->nullable();
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['indicator_id', 'changed_at'], 'me_indicator_code_history_idx');
        });

        Schema::table('me_performance_reports', function (Blueprint $table): void {
            $table->dropUnique('me_performance_report_owner_period_unique');
            $table->string('reporting_period_type', 24)->default('quarter')->after('reporting_quarter');
            $table->string('reporting_period_label', 40)->nullable()->after('reporting_period_type');
            $table->foreignUuid('verified_by')->nullable()->after('review_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('verification_notes')->nullable()->after('verified_at');
            $table->foreignUuid('approved_by')->nullable()->after('verification_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
        });

        Schema::table('me_performance_report_indicator_results', function (Blueprint $table): void {
            $table->decimal('rollup_numerator', 20, 4)->nullable()->after('actual_value');
            $table->decimal('rollup_denominator', 20, 4)->nullable()->after('rollup_numerator');
        });

        DB::table('me_performance_reports')->update([
            'reporting_period_type' => 'quarter',
            'reporting_period_label' => DB::raw('reporting_quarter'),
        ]);
        DB::table('me_performance_reports')
            ->where('status', 'reviewed')
            ->update([
                'status' => 'approved',
                'verified_by' => DB::raw('reviewed_by'),
                'verified_at' => DB::raw('reviewed_at'),
                'verification_notes' => DB::raw('review_notes'),
                'approved_by' => DB::raw('reviewed_by'),
                'approved_at' => DB::raw('reviewed_at'),
                'approval_notes' => DB::raw('review_notes'),
            ]);

        Schema::table('me_performance_reports', function (Blueprint $table): void {
            $table->unique(
                ['form_id', 'reporting_year', 'reporting_period_type', 'reporting_period_label', 'think_tank_member_id'],
                'me_performance_report_owner_general_period_unique'
            );
            $table->index(
                ['reporting_year', 'reporting_period_type', 'reporting_period_label'],
                'me_performance_report_general_period_idx'
            );
        });

        Schema::table('me_knowledge_evidence_items', function (Blueprint $table): void {
            $table->string('repository_category', 30)->default('knowledge')->after('document_type');
            $table->string('checksum_sha256', 64)->nullable()->after('file_size');
            $table->unsignedInteger('version_number')->default(1)->after('checksum_sha256');
            $table->foreignUuid('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->foreignUuid('retired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['repository_category', 'document_type'], 'me_repository_category_type_idx');
            $table->index('checksum_sha256', 'me_repository_checksum_idx');
        });

        Schema::create('me_repository_document_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_item_id')->constrained('me_knowledge_evidence_items')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->text('change_notes')->nullable();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['repository_item_id', 'version_number'], 'me_repository_version_unique');
        });

        Schema::create('me_repository_document_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_item_id')->constrained('me_knowledge_evidence_items')->cascadeOnDelete();
            $table->string('linkable_type', 180);
            $table->uuid('linkable_id');
            $table->string('purpose', 60)->default('supporting_evidence');
            $table->foreignUuid('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['repository_item_id', 'linkable_type', 'linkable_id', 'purpose'],
                'me_repository_document_link_unique'
            );
            $table->index(['linkable_type', 'linkable_id'], 'me_repository_linkable_idx');
        });

        Schema::table('me_performance_report_documents', function (Blueprint $table): void {
            $table->foreignUuid('repository_item_id')
                ->nullable()
                ->after('report_id')
                ->constrained('me_knowledge_evidence_items')
                ->nullOnDelete();
            $table->index('repository_item_id', 'me_report_document_repository_idx');
        });

        Schema::create('me_indicator_achievements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('report_id')->constrained('me_performance_reports')->cascadeOnDelete();
            $table->foreignUuid('report_indicator_result_id')
                ->constrained('me_performance_report_indicator_results')
                ->cascadeOnDelete();
            $table->foreignUuid('indicator_id')->constrained('myb_indicators')->restrictOnDelete();
            $table->foreignUuid('indicator_result_id')->nullable()->constrained('me_indicator_results')->nullOnDelete();
            $table->string('achievement_code', 100)->unique();
            $table->string('title');
            $table->text('description');
            $table->date('achieved_on');
            $table->string('geographic_scope', 40)->nullable();
            $table->string('country', 120)->nullable();
            $table->string('rec', 80)->nullable();
            $table->string('location')->nullable();
            $table->foreignUuid('lead_think_tank_member_id')->nullable()->constrained('attp_consortium_think_tanks')->nullOnDelete();
            $table->json('collaborating_institutions')->nullable();
            $table->json('priority_themes')->nullable();
            $table->unsignedBigInteger('total_beneficiaries')->default(0);
            $table->string('verification_status', 30)->default('draft');
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['report_id', 'indicator_id'], 'me_achievement_report_indicator_idx');
            $table->index(['country', 'rec'], 'me_achievement_geography_idx');
            $table->index('verification_status', 'me_achievement_verification_idx');
        });

        Schema::create('me_indicator_achievement_disaggregations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('achievement_id')->constrained('me_indicator_achievements')->cascadeOnDelete();
            $table->string('geographic_scope', 40)->nullable();
            $table->string('country', 120)->nullable();
            $table->string('rec', 80)->nullable();
            $table->string('implementing_institution_type', 50)->nullable();
            $table->string('implementing_institution')->nullable();
            $table->string('priority_theme', 100)->nullable();
            $table->string('gender', 40)->nullable();
            $table->string('age_group', 40)->nullable();
            $table->string('stakeholder_category', 80)->nullable();
            $table->unsignedBigInteger('beneficiary_count')->default(0);
            $table->string('combination_hash', 64);
            $table->json('additional_dimensions')->nullable();
            $table->timestamps();

            $table->unique(['achievement_id', 'combination_hash'], 'me_achievement_disagg_combination_unique');
            $table->index(['gender', 'age_group', 'stakeholder_category'], 'me_achievement_beneficiary_idx');
            $table->index(['country', 'rec', 'priority_theme'], 'me_achievement_classification_idx');
        });

        Schema::create('me_matrix_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('portfolio_id')->nullable()->constrained('myb_sectors')->nullOnDelete();
            $table->foreignUuid('repository_item_id')->constrained('me_knowledge_evidence_items')->restrictOnDelete();
            $table->string('title');
            $table->string('matrix_code', 80);
            $table->unsignedInteger('version_number');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('change_summary')->nullable();
            $table->json('import_summary')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['matrix_code', 'version_number'], 'me_matrix_code_version_unique');
            $table->index(['portfolio_id', 'status'], 'me_matrix_portfolio_status_idx');
        });

        $this->reconcileExactErfDuplicateWhenSafe();
        $this->seedDisaggregationTaxonomy();
        $this->seedFocalUnitContacts();
    }

    public function down(): void
    {
        Schema::dropIfExists('me_matrix_versions');
        Schema::dropIfExists('me_indicator_achievement_disaggregations');
        Schema::dropIfExists('me_indicator_achievements');
        Schema::dropIfExists('me_focal_unit_contacts');

        Schema::table('me_performance_report_documents', function (Blueprint $table): void {
            $table->dropIndex('me_report_document_repository_idx');
            $table->dropConstrainedForeignId('repository_item_id');
        });

        Schema::dropIfExists('me_repository_document_links');
        Schema::dropIfExists('me_repository_document_versions');

        Schema::table('me_performance_report_indicator_results', function (Blueprint $table): void {
            $table->dropColumn(['rollup_numerator', 'rollup_denominator']);
        });

        Schema::table('me_knowledge_evidence_items', function (Blueprint $table): void {
            $table->dropIndex('me_repository_checksum_idx');
            $table->dropIndex('me_repository_category_type_idx');
            $table->dropConstrainedForeignId('retired_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'repository_category',
                'checksum_sha256',
                'version_number',
                'retired_at',
            ]);
        });

        Schema::table('me_performance_reports', function (Blueprint $table): void {
            $table->dropIndex('me_performance_report_general_period_idx');
            $table->dropUnique('me_performance_report_owner_general_period_unique');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn([
                'reporting_period_type',
                'reporting_period_label',
                'verified_at',
                'verification_notes',
                'approved_at',
                'approval_notes',
            ]);
            $table->unique(
                ['form_id', 'reporting_year', 'reporting_quarter', 'think_tank_member_id'],
                'me_performance_report_owner_period_unique'
            );
        });

        Schema::dropIfExists('me_indicator_code_histories');

        Schema::table('myb_indicators', function (Blueprint $table): void {
            $table->dropIndex('me_indicators_org_rollup_idx');
            $table->dropConstrainedForeignId('code_updated_by');
            $table->dropColumn('organization_rollup_method');
        });

        Schema::dropIfExists('me_indicator_disaggregation_requirements');
        Schema::dropIfExists('me_disaggregation_options');
        Schema::dropIfExists('me_disaggregation_dimensions');
    }

    private function seedDisaggregationTaxonomy(): void
    {
        $now = now();
        $dimensions = [
            'geographic_scope' => ['Geographic scope', 'classification', 'Country, national, REC, or regional scope.', 10],
            'country' => ['Country', 'classification', 'Country where an achievement or beneficiary is located.', 20],
            'rec' => ['Regional Economic Community (REC)', 'classification', 'Recognized African Regional Economic Community.', 30],
            'implementing_institution_type' => ['Implementing institution type', 'classification', 'Think tank, consortium, or partner institution.', 40],
            'priority_theme' => ['ATTP priority thematic area', 'classification', 'Approved ATTP priority thematic area.', 50],
            'gender' => ['Gender', 'beneficiary', 'Beneficiary gender category.', 60],
            'age_group' => ['Age group', 'beneficiary', 'Youth below 35 or adult aged 35 and above.', 70],
            'stakeholder_category' => ['Stakeholder category', 'beneficiary', 'ATTP stakeholder classification.', 80],
        ];

        $dimensionIds = [];
        foreach ($dimensions as $code => [$name, $group, $description, $sort]) {
            $id = (string) Str::uuid();
            $dimensionIds[$code] = $id;
            DB::table('me_disaggregation_dimensions')->insert([
                'id' => $id,
                'code' => $code,
                'name' => $name,
                'dimension_group' => $group,
                'description' => $description,
                'sort_order' => $sort,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $options = [
            'geographic_scope' => [
                'country' => 'Country',
                'national' => 'National',
                'rec' => 'Regional Economic Community (REC)',
                'regional' => 'Regional / multi-country',
            ],
            'rec' => [
                'amu' => 'Arab Maghreb Union (AMU)',
                'cen_sad' => 'Community of Sahel-Saharan States (CEN-SAD)',
                'comesa' => 'Common Market for Eastern and Southern Africa (COMESA)',
                'eac' => 'East African Community (EAC)',
                'eccas' => 'Economic Community of Central African States (ECCAS)',
                'ecowas' => 'Economic Community of West African States (ECOWAS)',
                'igad' => 'Intergovernmental Authority on Development (IGAD)',
                'sadc' => 'Southern African Development Community (SADC)',
            ],
            'implementing_institution_type' => [
                'think_tank' => 'Think tank',
                'consortium' => 'Consortium',
                'partner_institution' => 'Partner institution',
            ],
            'priority_theme' => [
                'economic_transformation_governance' => 'Economic Transformation and Governance',
                'climate_change' => 'Climate Change',
                'regional_trade' => 'Regional Trade',
                'food_security' => 'Food Security',
                'human_capital' => 'Human Capital',
                'digitalization' => 'Digitalization',
            ],
            'gender' => [
                'female' => 'Female',
                'male' => 'Male',
                'other_not_reported' => 'Other / not reported',
            ],
            'age_group' => [
                'youth_below_35' => 'Youth below 35 years',
                'adult_35_plus' => 'Adults aged 35 years and above',
                'not_reported' => 'Not reported',
            ],
            'stakeholder_category' => [
                'government' => 'Government',
                'parliament' => 'Parliament',
                'regional_organization' => 'Regional organization',
                'think_tank' => 'Think tank',
                'academia' => 'Academia',
                'civil_society' => 'Civil society',
                'private_sector' => 'Private sector',
                'development_partner' => 'Development partner',
                'media' => 'Media',
                'other' => 'Other',
            ],
        ];

        $countries = [
            'algeria' => 'Algeria', 'angola' => 'Angola', 'benin' => 'Benin', 'botswana' => 'Botswana',
            'burkina_faso' => 'Burkina Faso', 'burundi' => 'Burundi', 'cabo_verde' => 'Cabo Verde',
            'cameroon' => 'Cameroon', 'central_african_republic' => 'Central African Republic', 'chad' => 'Chad',
            'comoros' => 'Comoros', 'congo' => 'Congo', 'cote_divoire' => "Côte d’Ivoire",
            'democratic_republic_of_congo' => 'Democratic Republic of the Congo', 'djibouti' => 'Djibouti',
            'egypt' => 'Egypt', 'equatorial_guinea' => 'Equatorial Guinea', 'eritrea' => 'Eritrea',
            'eswatini' => 'Eswatini', 'ethiopia' => 'Ethiopia', 'gabon' => 'Gabon', 'gambia' => 'The Gambia',
            'ghana' => 'Ghana', 'guinea' => 'Guinea', 'guinea_bissau' => 'Guinea-Bissau', 'kenya' => 'Kenya',
            'lesotho' => 'Lesotho', 'liberia' => 'Liberia', 'libya' => 'Libya', 'madagascar' => 'Madagascar',
            'malawi' => 'Malawi', 'mali' => 'Mali', 'mauritania' => 'Mauritania', 'mauritius' => 'Mauritius',
            'morocco' => 'Morocco', 'mozambique' => 'Mozambique', 'namibia' => 'Namibia', 'niger' => 'Niger',
            'nigeria' => 'Nigeria', 'rwanda' => 'Rwanda', 'sao_tome_principe' => 'São Tomé and Príncipe',
            'senegal' => 'Senegal', 'seychelles' => 'Seychelles', 'sierra_leone' => 'Sierra Leone',
            'somalia' => 'Somalia', 'south_africa' => 'South Africa', 'south_sudan' => 'South Sudan',
            'sudan' => 'Sudan', 'tanzania' => 'Tanzania', 'togo' => 'Togo', 'tunisia' => 'Tunisia',
            'uganda' => 'Uganda', 'zambia' => 'Zambia', 'zimbabwe' => 'Zimbabwe',
        ];
        $options['country'] = $countries;

        foreach ($options as $dimensionCode => $dimensionOptions) {
            $sort = 10;
            foreach ($dimensionOptions as $code => $name) {
                DB::table('me_disaggregation_options')->insert([
                    'id' => (string) Str::uuid(),
                    'dimension_id' => $dimensionIds[$dimensionCode],
                    'parent_id' => null,
                    'code' => $code,
                    'name' => $name,
                    'metadata' => null,
                    'sort_order' => $sort,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $sort += 10;
            }
        }
    }

    private function reconcileExactErfDuplicateWhenSafe(): void
    {
        $canonical = DB::table('attp_consortium_think_tanks')->where('name', 'Economic Research Forum')->first();
        $duplicate = DB::table('attp_consortium_think_tanks')->where('name', 'Economic Research Forum (ERF)')->first();
        if (! $canonical || ! $duplicate) {
            return;
        }

        $referenceTables = [
            'me_data_collection_assignments', 'me_indicator_results', 'me_performance_reports',
            'attp_activity_reports', 'attp_fund_allocations', 'attp_disbursement_requests',
            'procurements', 'procurement_disbursements', 'procurement_purchase_orders',
            'attp_think_tank_procurement_plans', 'attp_think_tank_research_outputs',
            'me_mission_reports', 'biannual_site_visit_profiles',
        ];
        $hasBusinessRecords = collect($referenceTables)->contains(function (string $table) use ($duplicate): bool {
            return Schema::hasTable($table)
                && Schema::hasColumn($table, 'think_tank_member_id')
                && DB::table($table)->where('think_tank_member_id', $duplicate->id)->exists();
        });
        if ($hasBusinessRecords) {
            return;
        }

        DB::table('users')->where('think_tank_member_id', $duplicate->id)->update([
            'think_tank_member_id' => $canonical->id,
            'updated_at' => now(),
        ]);
        DB::table('attp_consortium_think_tanks')->where('id', $duplicate->id)->update([
            'status' => 'inactive',
            'updated_at' => now(),
        ]);
    }

    private function seedFocalUnitContacts(): void
    {
        $contacts = [
            ['RAISE AFRICA', 'ERF', 'Ms. Yasmine Fahim', 'yfahim@erf.org.eg', false, null],
            ['RAISE AFRICA', 'ERF', 'Ms. Yasmeen Oraby', 'yoraby@erf.org.eg', false, null],
            ['RAISE AFRICA', 'PEP', 'Mr. Dickson Otiangala', 'dickson.otiangala@pep-net.org', false, null],
            ['RAISE AFRICA', 'PEP', 'Ms. Ana Badillo', 'ana.badillo@pep-net.org', false, null],
            ['RAISE AFRICA', 'REPRC-UNN', 'Prof. Innocent Ifelunini', 'innocent.ifelunini@unn.edu.ng', false, null],
            ['CACEPS', 'APHRC', 'Meshack Johnson', 'mjohnson@aphrc.org', true, 'Contact person'],
            ['CACEPS', 'APHRC', 'Judy Ihiga', 'jihiga@aphrc.org', false, null],
            ['CACEPS', 'APHRC', 'Billy Lubuya', 'blubuya@aphrc.org', false, null],
            ['CACEPS', 'CPED', 'Mercy Edejeghwro', 'omueromercy21@gmail.com', false, null],
            ['CACEPS', 'CIP', 'Julia Zita', 'julia.zita@cipmoz.org', false, null],
            ['CACEPS', 'IPAR', 'Awa Dia', 'awa.dia@ipar.sn', true, 'Contact person'],
            ['CACEPS', 'IPAR', 'Oumar Gueye', 'oumar.gueye@ipar.sn', false, null],
            ['CACEPS', 'ECES', 'Seif Khawanky', 'seif.khawanky@gmail.com', false, null],
            ['BRIDGE', 'ACET', 'Iana Dadzie', 'ddadzie@acetforafrica.org', false, null],
            ['BRIDGE', 'AFIDEP', 'Lwama Kamanga', 'lwama.kamanga@afidep.org', false, null],
            ['BRIDGE', 'Policy Center', 'Asmaa Tahraoui', 'a.tahraoui@policycenter.ma', false, null],
            ['BRIDGE', 'Foretia Foundation', 'Bruno Ittia', 'bachuo@foretiafoundation.org', false, null],
            ['BRIDGE', 'SAIIA', 'Goodwill Kachingwe', 'goodwill.kachingwe@saiia.org.za', true, 'SAIIA ATTP Project Coordinator'],
        ];
        $aliases = [
            'ERF' => 'economic research forum', 'PEP' => 'partnership for economic policy',
            'REPRC-UNN' => 'resource and environmental policy', 'APHRC' => 'african population and health',
            'CPED' => 'population and environmental development', 'CIP' => 'centro de integridade',
            'IPAR' => 'initiative prospective', 'ECES' => 'egyptian center for economic studies',
            'ACET' => 'economic transformation', 'AFIDEP' => 'african institute for development policy',
            'Policy Center' => 'policy center for the new south', 'Foretia Foundation' => 'foretia foundation',
            'SAIIA' => 'international affairs',
        ];
        $members = DB::table('attp_consortium_think_tanks')->where('status', 'active')->get(['id', 'name']);
        $users = DB::table('users')->get(['id', 'email'])->keyBy(fn (object $user) => strtolower((string) $user->email));

        foreach ($contacts as [$consortium, $label, $name, $email, $primary, $notes]) {
            $needle = $aliases[$label];
            $member = $members->first(fn (object $row) => str_contains(strtolower($row->name), $needle));
            $user = $users->get(strtolower($email));
            DB::table('me_focal_unit_contacts')->updateOrInsert(
                ['email' => strtolower($email)],
                [
                    'id' => (string) Str::uuid(),
                    'consortium_name' => $consortium,
                    'think_tank_member_id' => $member?->id,
                    'think_tank_label' => $label,
                    'user_id' => $user?->id,
                    'focal_person_name' => $name,
                    'is_primary' => $primary,
                    'notes' => $notes,
                    'source' => 'ATTP unified tracker supplied 2026-08-02',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
};
