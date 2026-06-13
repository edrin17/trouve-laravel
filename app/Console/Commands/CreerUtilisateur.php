<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;

/**
 * Crée un utilisateur ou réinitialise son mot de passe (saisi en masqué).
 * Sert à gérer les comptes sans inscription publique ni édition de code.
 */
#[Signature('app:creer-utilisateur {name : Nom affiché} {email : Adresse e-mail}')]
#[Description('Crée un utilisateur (ou met à jour son mot de passe) avec saisie masquée du mot de passe')]
class CreerUtilisateur extends Command
{
    public function handle(): int
    {
        $name = $this->argument('name');
        $email = $this->argument('email');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email']],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $erreur) {
                $this->error($erreur);
            }
            return self::FAILURE;
        }

        $motDePasse = password(
            label: 'Mot de passe',
            validate: fn (string $value) => strlen($value) < 8 ? 'Le mot de passe doit faire au moins 8 caractères.' : null,
        );

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($motDePasse)],
        );

        $this->info(
            $user->wasRecentlyCreated
                ? "Utilisateur « {$name} » créé ({$email})."
                : "Mot de passe de « {$name} » mis à jour ({$email})."
        );

        return self::SUCCESS;
    }
}
