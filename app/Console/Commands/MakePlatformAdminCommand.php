<?php

namespace App\Console\Commands;

use App\Modules\Platform\Services\MakePlatformAdminService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MakePlatformAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:make-admin {email : The email of the user to promote}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promote a user to Platform Administrator';

    /**
     * Execute the console command.
     */
    public function handle(MakePlatformAdminService $service)
    {
        $email = $this->argument('email');

        try {
            $service->execute($email);
            $this->info("User {$email} successfully promoted to Platform Administrator.");
            return Command::SUCCESS;
        } catch (ModelNotFoundException $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error("An unexpected error occurred: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
