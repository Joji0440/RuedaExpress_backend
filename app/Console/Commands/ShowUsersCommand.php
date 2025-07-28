<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShowUsersCommand extends Command
{
    protected $signature = 'app:show-users';
    protected $description = 'Mostrar usuarios disponibles';

    public function handle()
    {
        $this->info('=== USUARIOS DISPONIBLES ===');
        
        $users = DB::table('users')->get(['id', 'name', 'email']);
        
        foreach ($users as $user) {
            $this->line("{$user->id} - {$user->name} ({$user->email})");
        }

        return 0;
    }
}
