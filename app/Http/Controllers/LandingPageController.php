<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LandingPageController extends Controller
{

    public function index()
    {
        // Fetch all categories and their related open projects
        // $categories = Category::with(['projects' => function ($query) {
        //     $query->where('status', 'open');
        // }])->get();

        // Or: Fetch all open projects without category grouping
        // $projects = Project::where('status', 'open')->with('category')->latest()->get();

        return view('welcome');
    }

    public function showBid(Project $project)
    {
        return view('landing.show', compact('project'));
    }

    public function create()
    {
        return view('about');
    }

    public function contact()
    {
        return view('contact');
    }

    public function africanMap()
    {
        $africaPath = public_path('assets/Africa');
        $shapeFiles = [];

        if (File::exists($africaPath)) {
            $baseUrl = app()->bound('request') ? rtrim(request()->getBaseUrl(), '/') : '';
            $assetPathPrefix = ($baseUrl !== '' ? $baseUrl : '') . '/assets/Africa/';

            $shapeFiles = collect(File::files($africaPath))
                ->filter(function ($file) {
                    return strtolower($file->getExtension()) === 'shp';
                })
                ->sortBy(function ($file) {
                    return $file->getFilename();
                })
                ->map(function ($file) use ($assetPathPrefix) {
                    return $assetPathPrefix . rawurlencode($file->getFilename());
                })
                ->values()
                ->all();
        }

        return view('african-map', compact('shapeFiles'));
    }

    public function impactMap()
    {
        // Comprehensive dummy data for funding partners and projects by country/region
        $impactData = [
            'funding_partners' => [
                [
                    'id' => 1,
                    'name' => 'African Development Bank',
                    'amount' => 250000000,
                    'projects' => 45,
                    'countries' => 32,
                    'focus' => 'Infrastructure, Agriculture',
                    'impact_summary' => 'Transformed infrastructure across 32 African nations, reaching 15M beneficiaries',
                    'beneficiary_countries' => ['Nigeria', 'Kenya', 'Egypt', 'South Africa', 'Ghana', 'Ethiopia', 'Tanzania', 'Morocco', 'Senegal', 'Uganda']
                ],
                [
                    'id' => 2,
                    'name' => 'World Bank Group',
                    'amount' => 180000000,
                    'projects' => 32,
                    'countries' => 28,
                    'focus' => 'Education, Health',
                    'impact_summary' => 'Improved education and healthcare systems, benefiting 12M people',
                    'beneficiary_countries' => ['Nigeria', 'Kenya', 'Ethiopia', 'Tanzania', 'Uganda', 'Ghana', 'Senegal', 'Rwanda']
                ],
                [
                    'id' => 3,
                    'name' => 'European Union',
                    'amount' => 150000000,
                    'projects' => 28,
                    'countries' => 25,
                    'focus' => 'Governance, Trade',
                    'impact_summary' => 'Strengthened governance frameworks and trade partnerships in 25 countries',
                    'beneficiary_countries' => ['Egypt', 'Morocco', 'Tunisia', 'South Africa', 'Kenya', 'Ghana', 'Senegal']
                ],
                [
                    'id' => 4,
                    'name' => 'UN Development Programme',
                    'amount' => 120000000,
                    'projects' => 38,
                    'countries' => 35,
                    'focus' => 'SDGs, Climate',
                    'impact_summary' => 'Climate resilience and SDG implementation across 35 member states',
                    'beneficiary_countries' => ['Nigeria', 'Kenya', 'Ethiopia', 'South Africa', 'Egypt', 'Tanzania', 'Uganda', 'Ghana']
                ],
                [
                    'id' => 5,
                    'name' => 'Gates Foundation',
                    'amount' => 90000000,
                    'projects' => 15,
                    'countries' => 18,
                    'focus' => 'Health, Agriculture',
                    'impact_summary' => 'Revolutionary healthcare and agricultural interventions reaching 8M people',
                    'beneficiary_countries' => ['Nigeria', 'Kenya', 'Ethiopia', 'Tanzania', 'Uganda', 'Ghana']
                ],
                [
                    'id' => 6,
                    'name' => 'USAID',
                    'amount' => 85000000,
                    'projects' => 24,
                    'countries' => 22,
                    'focus' => 'Democracy, Economic Growth',
                    'impact_summary' => 'Democratic strengthening and economic empowerment initiatives',
                    'beneficiary_countries' => ['Nigeria', 'Kenya', 'South Africa', 'Ghana', 'Senegal', 'Ethiopia']
                ],
                [
                    'id' => 7,
                    'name' => 'UK Aid Direct',
                    'amount' => 75000000,
                    'projects' => 19,
                    'countries' => 16,
                    'focus' => 'Education, Gender',
                    'impact_summary' => 'Gender equality and education access programs impacting 6M women and girls',
                    'beneficiary_countries' => ['Kenya', 'Tanzania', 'Uganda', 'Ghana', 'Rwanda', 'Malawi']
                ],
            ],
            'projects' => [
                // African Development Bank Projects
                ['id' => 1, 'partner_id' => 1, 'name' => 'Trans-African Highway Infrastructure', 'amount' => 45000000, 'region' => 'West Africa', 'countries' => ['Nigeria', 'Ghana', 'Senegal'], 'outcome' => '850km of highway completed, reducing transport time by 40%', 'beneficiaries' => 2500000, 'status' => 'Completed'],
                ['id' => 2, 'partner_id' => 1, 'name' => 'Agricultural Mechanization Program', 'amount' => 35000000, 'region' => 'East Africa', 'countries' => ['Kenya', 'Ethiopia', 'Uganda'], 'outcome' => '50,000 farmers equipped with modern tools, 35% yield increase', 'beneficiaries' => 250000, 'status' => 'Ongoing'],
                ['id' => 3, 'partner_id' => 1, 'name' => 'Renewable Energy Grid Expansion', 'amount' => 60000000, 'region' => 'Southern Africa', 'countries' => ['South Africa', 'Zambia'], 'outcome' => '2.5M households connected to clean energy', 'beneficiaries' => 12500000, 'status' => 'Completed'],

                // World Bank Projects
                ['id' => 4, 'partner_id' => 2, 'name' => 'Primary Education Enhancement', 'amount' => 40000000, 'region' => 'East Africa', 'countries' => ['Kenya', 'Tanzania', 'Uganda'], 'outcome' => '1,200 schools built, 95% enrollment rate achieved', 'beneficiaries' => 3000000, 'status' => 'Completed'],
                ['id' => 5, 'partner_id' => 2, 'name' => 'Maternal Health Initiative', 'amount' => 32000000, 'region' => 'West Africa', 'countries' => ['Nigeria', 'Ghana', 'Senegal'], 'outcome' => 'Maternal mortality reduced by 45% in target areas', 'beneficiaries' => 2500000, 'status' => 'Ongoing'],
                ['id' => 6, 'partner_id' => 2, 'name' => 'Rural Healthcare Access Program', 'amount' => 28000000, 'region' => 'East Africa', 'countries' => ['Ethiopia', 'Rwanda'], 'outcome' => '250 health clinics established in remote areas', 'beneficiaries' => 1800000, 'status' => 'Completed'],

                // European Union Projects
                ['id' => 7, 'partner_id' => 3, 'name' => 'Trade Facilitation and Customs Reform', 'amount' => 35000000, 'region' => 'North Africa', 'countries' => ['Egypt', 'Morocco', 'Tunisia'], 'outcome' => 'Border processing time reduced by 60%, trade volume up 28%', 'beneficiaries' => 500000, 'status' => 'Completed'],
                ['id' => 8, 'partner_id' => 3, 'name' => 'Democratic Governance Support', 'amount' => 25000000, 'region' => 'West Africa', 'countries' => ['Ghana', 'Senegal'], 'outcome' => 'Electoral systems strengthened, 85% voter confidence', 'beneficiaries' => 1500000, 'status' => 'Ongoing'],

                // UN Development Programme Projects
                ['id' => 9, 'partner_id' => 4, 'name' => 'Climate Resilient Agriculture', 'amount' => 30000000, 'region' => 'East Africa', 'countries' => ['Ethiopia', 'Kenya', 'Tanzania'], 'outcome' => '80,000 farmers trained in climate-smart techniques', 'beneficiaries' => 400000, 'status' => 'Ongoing'],
                ['id' => 10, 'partner_id' => 4, 'name' => 'SDG Localization Program', 'amount' => 22000000, 'region' => 'West Africa', 'countries' => ['Nigeria', 'Ghana'], 'outcome' => '15 SDG implementation frameworks adopted at local level', 'beneficiaries' => 5000000, 'status' => 'Ongoing'],

                // Gates Foundation Projects
                ['id' => 11, 'partner_id' => 5, 'name' => 'Malaria Eradication Initiative', 'amount' => 38000000, 'region' => 'West Africa', 'countries' => ['Nigeria', 'Ghana'], 'outcome' => 'Malaria cases reduced by 68% in intervention zones', 'beneficiaries' => 4500000, 'status' => 'Ongoing'],
                ['id' => 12, 'partner_id' => 5, 'name' => 'Smallholder Farmer Digital Platform', 'amount' => 18000000, 'region' => 'East Africa', 'countries' => ['Kenya', 'Tanzania', 'Uganda'], 'outcome' => '120,000 farmers connected to markets via mobile platform', 'beneficiaries' => 600000, 'status' => 'Completed'],

                // USAID Projects
                ['id' => 13, 'partner_id' => 6, 'name' => 'Youth Economic Empowerment', 'amount' => 25000000, 'region' => 'West Africa', 'countries' => ['Nigeria', 'Ghana', 'Senegal'], 'outcome' => '45,000 youth trained and employed in digital economy', 'beneficiaries' => 225000, 'status' => 'Ongoing'],
                ['id' => 14, 'partner_id' => 6, 'name' => 'Democracy and Transparency Program', 'amount' => 20000000, 'region' => 'East Africa', 'countries' => ['Kenya'], 'outcome' => 'Transparency index improved by 32 points', 'beneficiaries' => 1000000, 'status' => 'Completed'],

                // UK Aid Direct Projects
                ['id' => 15, 'partner_id' => 7, 'name' => 'Girls Education Advancement', 'amount' => 28000000, 'region' => 'East Africa', 'countries' => ['Kenya', 'Tanzania', 'Uganda', 'Rwanda'], 'outcome' => '850,000 girls enrolled in secondary education', 'beneficiaries' => 850000, 'status' => 'Ongoing'],
                ['id' => 16, 'partner_id' => 7, 'name' => 'Women Economic Empowerment', 'amount' => 22000000, 'region' => 'West Africa', 'countries' => ['Ghana'], 'outcome' => '35,000 women entrepreneurs supported with microfinance', 'beneficiaries' => 175000, 'status' => 'Completed'],
            ],
            'regional_data' => [
                'North Africa' => ['projects' => 15, 'funding' => 85000000, 'partners' => 12, 'countries' => ['Egypt', 'Morocco', 'Tunisia', 'Algeria', 'Libya'], 'key_sectors' => ['Trade', 'Infrastructure', 'Tourism'], 'beneficiaries' => 3500000],
                'West Africa' => ['projects' => 42, 'funding' => 165000000, 'partners' => 18, 'countries' => ['Nigeria', 'Ghana', 'Senegal', 'Ivory Coast', 'Mali', 'Burkina Faso', 'Niger', 'Benin', 'Togo'], 'key_sectors' => ['Agriculture', 'Healthcare', 'Education'], 'beneficiaries' => 15000000],
                'Central Africa' => ['projects' => 28, 'funding' => 95000000, 'partners' => 10, 'countries' => ['Cameroon', 'Chad', 'Central African Republic', 'Democratic Republic of Congo', 'Gabon'], 'key_sectors' => ['Governance', 'Infrastructure'], 'beneficiaries' => 5000000],
                'East Africa' => ['projects' => 38, 'funding' => 145000000, 'partners' => 16, 'countries' => ['Kenya', 'Ethiopia', 'Tanzania', 'Uganda', 'Rwanda', 'Burundi', 'Somalia', 'Sudan'], 'key_sectors' => ['Agriculture', 'Education', 'Health'], 'beneficiaries' => 22000000],
                'Southern Africa' => ['projects' => 32, 'funding' => 120000000, 'partners' => 14, 'countries' => ['South Africa', 'Zimbabwe', 'Zambia', 'Mozambique', 'Botswana', 'Namibia', 'Angola'], 'key_sectors' => ['Energy', 'Technology', 'Mining'], 'beneficiaries' => 8500000],
            ],
            'country_data' => [
                'Nigeria' => ['projects' => 12, 'funding' => 45000000, 'sector' => 'Agriculture, Healthcare', 'region' => 'West Africa', 'population' => 206],
                'Kenya' => ['projects' => 10, 'funding' => 38000000, 'sector' => 'Education, Infrastructure', 'region' => 'East Africa', 'population' => 53],
                'South Africa' => ['projects' => 8, 'funding' => 32000000, 'sector' => 'Energy, Technology', 'region' => 'Southern Africa', 'population' => 59],
                'Egypt' => ['projects' => 7, 'funding' => 28000000, 'sector' => 'Infrastructure, Tourism', 'region' => 'North Africa', 'population' => 102],
                'Ghana' => ['projects' => 9, 'funding' => 25000000, 'sector' => 'Agriculture, Education', 'region' => 'West Africa', 'population' => 31],
                'Ethiopia' => ['projects' => 11, 'funding' => 35000000, 'sector' => 'Agriculture, Water', 'region' => 'East Africa', 'population' => 115],
                'Tanzania' => ['projects' => 8, 'funding' => 22000000, 'sector' => 'Healthcare, Education', 'region' => 'East Africa', 'population' => 59],
                'Morocco' => ['projects' => 6, 'funding' => 20000000, 'sector' => 'Energy, Manufacturing', 'region' => 'North Africa', 'population' => 37],
                'Uganda' => ['projects' => 7, 'funding' => 18000000, 'sector' => 'Healthcare, Agriculture', 'region' => 'East Africa', 'population' => 45],
                'Senegal' => ['projects' => 5, 'funding' => 15000000, 'sector' => 'Infrastructure, Fisheries', 'region' => 'West Africa', 'population' => 17],
            ],
        ];

        return view('impact-map', compact('impactData'));
    }

    public function submitInformationRequest(Request $request)
    {
        $validated = $request->validate([
            'requester_type' => 'required|string',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'country' => 'required|string|max:255',
            'organization' => 'nullable|string|max:255',
            'request_type' => 'required|string',
            'message' => 'required|string|max:2000',
        ]);

        // Store the request (you can create a database model for this later)
        // For now, we'll just send the email

        // Send acknowledgement email
        try {
            \Mail::to($validated['email'])->send(new \App\Mail\InformationRequestAcknowledgement($validated));

            return response()->json([
                'success' => true,
                'message' => 'Your request has been submitted successfully. You will receive an acknowledgement email shortly.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Request submitted but email could not be sent. We will process your request.'
            ], 500);
        }
    }

}
