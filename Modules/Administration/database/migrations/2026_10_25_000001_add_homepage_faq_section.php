<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Administration\Content\HomepageFaqDefaults;

return new class extends Migration
{
    public function up(): void
    {
        $content = json_encode(HomepageFaqDefaults::content(), JSON_THROW_ON_ERROR);
        DB::table('homepage_sections')->insert([
            'id' => 'faq', 'draft' => $content, 'published' => $content,
            'version' => 1, 'published_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('homepage_sections')->where('id', 'faq')->delete();
    }
};
