<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class InitialStaffSeeder extends Seeder
{
    public function run(): void
    {
        $username = strtolower(trim((string) config('pos.initial_username')));
        if (User::withTrashed()->where('username', $username)->exists()) {
            $this->command?->info('Initial username already exists; credentials were left unchanged.');

            return;
        }

        $data = [
            'role' => 'admin',
            'username' => $username,
            'full_name' => config('pos.initial_name'),
            'password' => config('pos.initial_password'),
        ];
        Validator::make($data, [
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_.-]+$/D'],
            'full_name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', function ($attribute, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('The initial password must not exceed 72 bytes.');
                }
            }],
        ])->validate();

        $data['password'] = Hash::make($data['password']);
        User::create($data);
        $this->command?->info('Initial staff account created.');
    }
}
