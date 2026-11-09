<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LandingPageBuilderMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function landing_pages_table_exists()
    {
        $this->assertTrue(Schema::hasTable('landing_pages'));
    }

    /** @test */
    public function landing_page_drafts_table_exists()
    {
        $this->assertTrue(Schema::hasTable('landing_page_drafts'));
    }

    /** @test */
    public function landing_page_revisions_table_exists()
    {
        $this->assertTrue(Schema::hasTable('landing_page_revisions'));
    }

    /** @test */
    public function landing_page_media_table_exists()
    {
        $this->assertTrue(Schema::hasTable('landing_page_media'));
    }

    /** @test */
    public function landing_pages_has_correct_columns()
    {
        $columns = Schema::getColumnListing('landing_pages');
        $this->assertContains('slug', $columns);
        $this->assertContains('title', $columns);
        $this->assertContains('status', $columns);
        $this->assertContains('published_revision_id', $columns);
        $this->assertContains('created_by', $columns);
        $this->assertContains('updated_by', $columns);
    }

    /** @test */
    public function landing_page_drafts_has_unique_landing_page_id()
    {
        $indexes = DB::select("SHOW INDEX FROM landing_page_drafts WHERE Key_name = 'landing_page_drafts_landing_page_id_unique'");
        $this->assertNotEmpty($indexes);
    }

    /** @test */
    public function landing_page_revisions_has_unique_revision_number_per_page()
    {
        $indexes = DB::select("SHOW INDEX FROM landing_page_revisions WHERE Key_name = 'landing_page_revisions_landing_page_id_revision_number_unique'");
        $this->assertNotEmpty($indexes);
    }

    /** @test */
    public function landing_page_media_has_unique_path()
    {
        $indexes = DB::select("SHOW INDEX FROM landing_page_media WHERE Key_name = 'landing_page_media_path_unique'");
        $this->assertNotEmpty($indexes);
    }

    /** @test */
    public function fk_users_id_user_exists()
    {
        // Verify FK references users.id_user
        $fks = DB::select("
            SELECT REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = 'percetakan_erp_testing'
            AND TABLE_NAME LIKE 'landing%'
            AND REFERENCED_TABLE_NAME = 'users'
        ");
        $this->assertNotEmpty($fks);
        foreach ($fks as $fk) {
            $this->assertEquals('id_user', $fk->REFERENCED_COLUMN_NAME);
        }
    }

    /** @test */
    public function fk_landing_pages_cascade()
    {
        $fks = DB::select("
            SELECT kcu.TABLE_NAME, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, rc.DELETE_RULE
            FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
            JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
            WHERE rc.CONSTRAINT_SCHEMA = 'percetakan_erp_testing'
            AND kcu.TABLE_NAME IN ('landing_page_drafts', 'landing_page_revisions', 'landing_page_media')
            AND kcu.REFERENCED_TABLE_NAME = 'landing_pages'
        ");
        $this->assertNotEmpty($fks);
        foreach ($fks as $fk) {
            $this->assertEquals('CASCADE', $fk->DELETE_RULE);
        }
    }

    /** @test */
    public function published_revision_id_set_null_on_delete()
    {
        $fks = DB::select("
            SELECT DELETE_RULE
            FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = 'percetakan_erp_testing'
            AND CONSTRAINT_NAME = 'landing_pages_published_revision_id_foreign'
        ");
        $this->assertNotEmpty($fks);
        $this->assertEquals('SET NULL', $fks[0]->DELETE_RULE);
    }

    /** @test */
    public function default_values_correct()
    {
        // landing_pages status default
        $col = DB::select("SHOW COLUMNS FROM landing_pages WHERE Field = 'status'");
        $this->assertEquals("enum('draft','published')", $col[0]->Type);
        $this->assertEquals('draft', $col[0]->Default);

        // landing_page_drafts version default
        $col = DB::select("SHOW COLUMNS FROM landing_page_drafts WHERE Field = 'version'");
        $this->assertEquals('1', $col[0]->Default);

        // landing_page_revisions schema_version default
        $col = DB::select("SHOW COLUMNS FROM landing_page_revisions WHERE Field = 'schema_version'");
        $this->assertEquals('1.0.0', $col[0]->Default);

        // landing_page_media disk default
        $col = DB::select("SHOW COLUMNS FROM landing_page_media WHERE Field = 'disk'");
        $this->assertEquals('public', $col[0]->Default);

        // landing_page_media size default
        $col = DB::select("SHOW COLUMNS FROM landing_page_media WHERE Field = 'size'");
        $this->assertEquals('0', $col[0]->Default);
    }

    /** @test */
    public function audit_logs_exists_and_compatible()
    {
        $this->assertTrue(Schema::hasTable('audit_logs'));
        $columns = Schema::getColumnListing('audit_logs');
        $this->assertContains('auditable_type', $columns);
        $this->assertContains('auditable_id', $columns);
        $this->assertContains('user_id', $columns);
        $this->assertContains('event', $columns);
        $this->assertContains('created_at', $columns);
    }

    /** @test */
    public function migration_idempotent()
    {
        // Re-running migration should not error
        $this->artisan('migrate')->assertExitCode(0);
    }
}