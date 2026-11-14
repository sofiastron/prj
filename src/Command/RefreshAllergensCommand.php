<?php

namespace App\Command;

use App\Entity\Ingredient;
use App\Service\AllergenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:allergens:refresh', description: 'Met à jour les allergènes des ingrédients munis d\'un code-barres (Open Food Facts)')]
class RefreshAllergensCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly AllergenService $allergens)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $items = $this->em->getRepository(Ingredient::class)->createQueryBuilder('i')
            ->where('i.barcode IS NOT NULL')->orderBy('i.name')->getQuery()->getResult();
        foreach ($items as $ingredient) {
            $io->writeln($this->allergens->refresh($ingredient));
        }
        $io->success(\count($items).' ingrédient(s) traité(s).');

        return Command::SUCCESS;
    }
}
