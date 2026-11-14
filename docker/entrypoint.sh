#!/bin/sh
set -e

# Attente de la base de données si DATABASE_URL est configuré
if [ -n "$DATABASE_URL" ]; then
    echo "Vérification de la connexion à la base de données..."
    max_retries=30
    count=0
    until php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1 || [ $count -ge $max_retries ]; do
        count=$((count + 1))
        echo "En attente de la base de données ($count/$max_retries)..."
        sleep 2
    done

    if [ $count -ge $max_retries ]; then
        echo "Avertissement : la base de données n'a pas répondu à temps, poursuite du démarrage..."
    else
        echo "Connexion à la base de données établie !"
        echo "Exécution des migrations Doctrine..."
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
    fi
fi

exec "$@"
