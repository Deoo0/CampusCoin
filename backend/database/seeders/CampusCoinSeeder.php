<?php

namespace Database\Seeders;

use App\Models\ServiceListing;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CampusCoinSeeder extends Seeder
{
    public function run(): void
    {
        // Demo seller — password is "password" for every seeded account
        $seller = User::firstOrCreate(
            ['email' => 'seller@campuscoin.test'],
            [
                'name' => 'Jordan Cruz',
                'password' => Hash::make('password'),
                'role' => 'STUDENT',
                'email_verified_at' => now(),
            ]
        );

        // The User::booted() created-hook only fires on ->create(), so
        // firstOrCreate() on an *existing* row won't retroactively add a
        // wallet — this covers re-running the seeder safely either way.
        $seller->wallet ?? $seller->wallet()->create(['cc_balance' => 0, 'cc_held' => 0]);

        $listings = [
            ['title' => 'Essay editing & proofreading', 'category' => 'EDITING', 'price_cc' => 8,
                'description' => 'Grammar, structure, and clarity pass on essays up to 2000 words. 24hr turnaround.'],
            ['title' => 'Thesis chapter review', 'category' => 'EDITING', 'price_cc' => 25,
                'description' => 'In-depth review of a single thesis/capstone chapter, with inline comments.'],
            ['title' => 'Bond paper printing (per 10 pages)', 'category' => 'PRINTING', 'price_cc' => 5,
                'description' => 'Black and white, short bond, ready for pickup at the library annex.'],
            ['title' => 'Colored printing (per 5 pages)', 'category' => 'PRINTING', 'price_cc' => 6,
                'description' => 'Full-color prints for posters, charts, or presentation handouts.'],
            ['title' => 'Landing page build (HTML/CSS)', 'category' => 'CODING', 'price_cc' => 40,
                'description' => 'Single responsive landing page from a Figma link or rough sketch.'],
            ['title' => 'Bug fix / debugging session', 'category' => 'CODING', 'price_cc' => 20,
                'description' => '1-hour paired debugging session on a school project, any common stack.'],
            ['title' => 'Logo design (3 concepts)', 'category' => 'DESIGN', 'price_cc' => 30,
                'description' => 'Three logo directions for an org, club, or personal brand, with one revision round.'],
            ['title' => 'Poster layout for an event', 'category' => 'OTHER', 'price_cc' => 15,
                'description' => 'Print-ready poster layout, content and images supplied by you.'],
        ];

        foreach ($listings as $listing) {
            ServiceListing::firstOrCreate(
                ['seller_id' => $seller->id, 'title' => $listing['title']],
                $listing + ['status' => 'ACTIVE']
            );
        }
    }
}
