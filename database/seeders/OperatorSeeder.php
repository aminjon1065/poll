<?php

namespace Database\Seeders;

use App\Models\Operator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OperatorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Operator::create([
            'name' => 'Admin Operator',
            'login' => 'admin-operator',
            'password' => Hash::make('password'),
            'max_chats' => 4,
        ]);
    }
}
