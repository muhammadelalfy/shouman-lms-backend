<?php

declare(strict_types=1);

namespace Modules\Identity\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Identity\Actions\CreateSystemAdministrator;
use Modules\Identity\Models\User;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\password;

/**
 * The password is prompted for, never accepted as an argument, so it does not
 * end up in shell history or process listings.
 */
#[AsCommand(name: 'identity:create-admin')]
final class CreateSystemAdministratorCommand extends Command
{
    protected $signature = 'identity:create-admin {email : Login email} {--name=System Administrator : Display name}';

    protected $description = 'Create a System Administrator account';

    public function handle(CreateSystemAdministrator $createAdministrator): int
    {
        $input = [
            'email' => Str::lower(trim((string) $this->argument('email'))),
            'name' => trim((string) $this->option('name')),
            'password' => password(label: 'Password', required: true),
        ];

        $validator = Validator::make($input, [
            'email' => ['required', 'email:rfc', 'max:254', Rule::unique(User::class, 'email')],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = $createAdministrator->handle($input['name'], $input['email'], $input['password']);

        $this->components->info("System Administrator #{$user->id} created for {$user->email}.");

        return self::SUCCESS;
    }
}
