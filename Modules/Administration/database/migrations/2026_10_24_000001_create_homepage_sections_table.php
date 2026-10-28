<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Administration\Content\HomepageNeedsDefaults;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->jsonb('draft');
            $table->jsonb('published');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
        $content = json_encode(HomepageNeedsDefaults::content(), JSON_THROW_ON_ERROR);
        DB::table('homepage_sections')->insert([
            'id' => 'needs', 'draft' => $content, 'published' => $content,
            'version' => 1, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $permission = Permission::findOrCreate('homepage.manage', 'web');
        Role::query()->where('name', 'system-administrator')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_sections');
        Permission::query()->where('name', 'homepage.manage')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
