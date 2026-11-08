<?php

namespace App\Console\Commands;

use App\Actions\CreateStarterCharacters;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Crea una cuenta con correo y contraseña desde la terminal.
 * Sirve para crear tu cuenta de administrador en el servidor, donde el registro está cerrado.
 *
 * Uso: php artisan user:create tu@correo.com --admin
 */
class CreateUserCommand extends Command
{
    protected $signature = 'user:create {email} {--name= : El nombre que se mostrará} {--admin : Hacerla administradora}';

    protected $description = 'Crea una cuenta con correo y contraseña';

    public function handle(CreateStarterCharacters $createStarterCharacters): int
    {
        $email = $this->argument('email');
        $name = $this->option('name') ?? $this->ask('Nombre');
        // secret(): lo que escribes no se ve en pantalla
        $password = $this->secret('Contraseña (al menos 8 caracteres)');
        $confirmation = $this->secret('Repite la contraseña');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'email' => ['required', 'email', 'unique:users,email'],
                'name' => ['required', 'string', 'max:40'],
                'password' => ['required', 'confirmed', Password::min(8)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create(['email' => $email, 'name' => $name, 'password' => $password]);

        if ($this->option('admin')) {
            $user->is_admin = true;
            $user->save();
        }

        $createStarterCharacters->handle($user);
        $this->info("Cuenta creada para {$email}".($this->option('admin') ? ', con permisos de administrador.' : '.'));

        return self::SUCCESS;
    }
}
