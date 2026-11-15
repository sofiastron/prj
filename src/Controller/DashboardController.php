<?php

namespace App\Controller;

use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(Request $request, DashboardService $dashboard): Response
    {
        $days = max(1, min(365, $request->query->getInt('days', 30)));
        $margins = $dashboard->dishMargins($days);
        usort($margins, fn ($a, $b) => (float) $b['marginPct'] <=> (float) $a['marginPct']);
        $suggestions = [];
        foreach ($dashboard->restockSuggestions() as $s) {
            $suggestions[$s['ingredient']->supplier?->name ?? 'Sans fournisseur'][] = $s;
        }
        ksort($suggestions);

        return $this->render('dashboard/index.html.twig', [
            'days' => $days,
            'kpis' => $dashboard->kpis($days),
            'margins' => $margins,
            'top' => $dashboard->topSellers($days),
            'suggestions' => $suggestions,
        ]);
    }
}
