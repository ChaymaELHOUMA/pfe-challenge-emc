<?php

namespace App\Command;

use App\Exception\RegleMetierException;
use App\Service\ImportLotService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:lot',
    description: 'Importe un lot de livres depuis un fichier CSV via Open Library.'
)]
class ImportLotCommand extends Command
{
    public function __construct(
        private ImportLotService $importLotService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('fichier', InputArgument::REQUIRED, 'Chemin vers le fichier CSV')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule l\'importation sans persister en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cheminFichier = (string) $input->getArgument('fichier');
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Importation en lot depuis CSV');
        $io->text(sprintf('Fichier cible : %s', $cheminFichier));
        if ($dryRun) {
            $io->warning('Mode simulation activé (--dry-run) : aucune modification ne sera enregistrée en base.');
        }

        try {
            $rapport = $this->importLotService->importerFichier($cheminFichier, $dryRun);
        } catch (RegleMetierException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error(sprintf('Une erreur inattendue est survenue : %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $io->section("Rapport d'exécution");
        $io->horizontalTable(
            ['Total lignes', 'Succès', 'Échecs', 'Temps total', 'Mode'],
            [[
                (string) $rapport->totalLignes,
                (string) $rapport->nbSucces,
                (string) $rapport->nbEchecs,
                sprintf('%.2f s', $rapport->dureeSecondes),
                $rapport->dryRun ? 'Simulation (--dry-run)' : 'Réel',
            ]]
        );

        if ($rapport->aDesEchecs()) {
            $io->section('Détail des erreurs rencontrées');
            $lignes = [];
            foreach ($rapport->echecs as $echec) {
                $lignes[] = [
                    (string) $echec['ligne'],
                    $echec['isbn'],
                    $echec['erreur'],
                ];
            }
            $io->table(['Ligne', 'ISBN', 'Raison du rejet'], $lignes);
        }

        if ($rapport->dryRun) {
            $io->info(sprintf(
                'Simulation terminée : %d document(s) auraient été importés avec succès, %d rejeté(s).',
                $rapport->nbSucces,
                $rapport->nbEchecs
            ));
            return Command::SUCCESS;
        }

        if ($rapport->nbSucces === 0 && $rapport->totalLignes > 0) {
            $io->error('Tous les documents du lot ont été rejetés.');
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Importation terminée : %d document(s) importé(s) avec succès (%d erreur(s)).',
            $rapport->nbSucces,
            $rapport->nbEchecs
        ));

        return Command::SUCCESS;
    }
}
