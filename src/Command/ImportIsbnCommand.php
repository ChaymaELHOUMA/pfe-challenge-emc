<?php

namespace App\Command;

use App\Exception\RegleMetierException;
use App\Service\ImportLivreService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:isbn',
    description: 'Importe un livre depuis Open Library via son ISBN (ISBN-10 ou ISBN-13).'
)]
class ImportIsbnCommand extends Command
{
    public function __construct(
        private ImportLivreService $importLivreService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('isbn', InputArgument::REQUIRED, 'ISBN du document à importer (ISBN-10 ou ISBN-13)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rawIsbn = (string) $input->getArgument('isbn');

        $io->title(sprintf("Import Open Library pour l'ISBN : %s", $rawIsbn));

        try {
            $edition = $this->importLivreService->importerParIsbn($rawIsbn);
        } catch (RegleMetierException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error(sprintf("Une erreur inattendue est survenue : %s", $e->getMessage()));
            return Command::FAILURE;
        }

        $oeuvre = $edition->getOeuvre();
        $auteurs = [];
        foreach ($oeuvre->getContributions() as $c) {
            $p = $c->getPersonne();
            if ($p !== null) {
                $auteurs[] = trim(($p->getPrenom() ? $p->getPrenom() . ' ' : '') . $p->getNom());
            }
        }

        $io->success('Livre importé avec succès !');

        $io->horizontalTable(
            ['Titre', 'Auteur(s)', 'Éditeur', 'Année', 'ISBN'],
            [[
                $oeuvre->getTitre(),
                !empty($auteurs) ? implode(', ', array_unique($auteurs)) : 'Inconnu',
                $edition->getEditeur(),
                (string) $edition->getAnnee(),
                $edition->getIsbn(),
            ]]
        );

        return Command::SUCCESS;
    }
}
