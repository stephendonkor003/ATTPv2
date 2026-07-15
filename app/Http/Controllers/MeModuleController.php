<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

class MeModuleController extends Controller
{
    private const SECTIONS = [
        'results-framework' => [
            'title' => 'Results Framework and Indicator Management',
            'icon' => 'feather-target',
        ],
        'data-entry-performance-tracking' => [
            'title' => 'Data Entry and Performance Tracking',
            'icon' => 'feather-edit-3',
        ],
        'data-quality-approval-workflow' => [
            'title' => 'Data Quality and Approval Workflow',
            'icon' => 'feather-check-circle',
        ],
        'reporting-dashboard' => [
            'title' => 'Reporting and Dashboard',
            'icon' => 'feather-bar-chart-2',
        ],
        'management-dashboard' => [
            'title' => 'Management Dashboard',
            'icon' => 'feather-monitor',
        ],
        'knowledge-evidence-repository' => [
            'title' => 'Knowledge and Evidence Repository',
            'subtitle' => 'MEAL plans, TOCs and pertinent documents',
            'icon' => 'feather-folder',
        ],
        'data-governance-framework' => [
            'title' => 'Data Governance Framework',
            'icon' => 'feather-shield',
        ],
    ];

    public function resultsFramework()
    {
        return redirect()->route('budget.me.indicators.index');
    }

    public function dataEntry()
    {
        return $this->show('data-entry-performance-tracking');
    }

    public function dataQuality()
    {
        return $this->show('data-quality-approval-workflow');
    }

    public function reportingDashboard()
    {
        return $this->show('reporting-dashboard');
    }

    public function managementDashboard()
    {
        return $this->show('management-dashboard');
    }

    public function knowledgeRepository()
    {
        return $this->show('knowledge-evidence-repository');
    }

    public function dataGovernance()
    {
        return $this->show('data-governance-framework');
    }

    private function show(string $key)
    {
        $section = self::SECTIONS[$key];
        $sections = self::SECTIONS;

        return view('me.module.show', compact('key', 'section', 'sections'));
    }
}
