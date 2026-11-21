<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    public function up(): void
    {
        if (! app()->environment(['local', 'testing']) || ! User::exists()) {
            return;
        }
        DB::transaction(function () {
            $renamed = 0;
            foreach ([['admin', 'Alex Santos', 'Gabriel'], ['cashier', 'Jamie Reyes', 'Kenneth'], ['trainee', 'Sam Cruz', 'Mendoza']] as [$old,$fullName,$new]) {
                $user = User::where('username', $old)->where('full_name', $fullName)->first();
                if (! $user || User::whereRaw('LOWER(username) = ?', [strtolower($new)])->exists()) {
                    continue;
                }
                $user->update(['username' => $new, 'full_name' => $new]);
                $renamed++;
            }
            if ($renamed && ! User::whereRaw('LOWER(username) = ?', ['catangay'])->exists()) {
                File::ensureDirectoryExists(storage_path('app/private/uploads'));
                File::copy(public_path('assets/samples/sample-staff-4.jpg'), storage_path('app/private/uploads/sample-staff-4.jpg'));
                User::create(['username' => 'Catangay', 'full_name' => 'Catangay', 'password' => 'Catangay123!', 'avatar' => 'sample-staff-4.jpg']);
            }
        });
    }

    public function down(): void
    {
        // Preserve account identities and any edits made after this migration.
    }
};
