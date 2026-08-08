<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_acceptance_runs', function (Blueprint $table): void {
            $table->char('project_fingerprint', 64)->nullable()->after('summary')->index();
            $table->json('fingerprint_payload')->nullable()->after('project_fingerprint');
        });

        Schema::create('release_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('production_acceptance_run_id')
                ->constrained('production_acceptance_runs')
                ->restrictOnDelete();
            $table->string('version', 64);
            $table->string('status', 20)->default('frozen')->comment('frozen, superseded')->index();
            $table->string('environment', 50);
            $table->string('source', 20)->default('web')->comment('web, cli');
            $table->char('project_fingerprint', 64);
            $table->string('git_commit', 64)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('frozen_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('frozen_at');
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_status', 20)->default('matched')->comment('matched, drifted')->index();
            $table->json('verification_summary')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'version'], 'release_candidates_tenant_version_unique');
            $table->index(['tenant_id', 'status', 'frozen_at'], 'release_candidates_status_idx');
        });

        Schema::create('release_candidate_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('release_candidate_id')->constrained('release_candidates')->cascadeOnDelete();
            $table->string('artifact_key', 80);
            $table->string('label', 160);
            $table->char('sha256', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['release_candidate_id', 'artifact_key'], 'release_candidate_artifact_unique');
            $table->index(['tenant_id', 'artifact_key'], 'release_candidate_artifacts_key_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_candidate_artifacts');
        Schema::dropIfExists('release_candidates');

        Schema::table('production_acceptance_runs', function (Blueprint $table): void {
            $table->dropIndex(['project_fingerprint']);
            $table->dropColumn(['project_fingerprint', 'fingerprint_payload']);
        });
    }
};