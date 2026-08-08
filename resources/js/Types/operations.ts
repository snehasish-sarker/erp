export interface OperationsHealthCheck {
    key: string;
    label: string;
    status: 'passed' | 'failed';
    critical: boolean;
    message: string;
}

export interface OperationsHealthReport {
    generated_at: string;
    environment: string;
    summary: {
        checks: number;
        passed: number;
        critical_failures: number;
        warnings: number;
        healthy: boolean;
    };
    checks: OperationsHealthCheck[];
    metrics: {
        queue_driver: string;
        queued_jobs: number;
        failed_jobs: number;
        storage_used_percent: number | null;
        php_version: string;
    };
}

export interface DatabasePerformanceReport {
    driver: string;
    version: string | null;
    database_size_bytes: number | null;
    top_tables: Array<{
        table: string;
        estimated_rows: number;
        data_bytes: number;
        index_bytes: number;
    }>;
    long_running_queries: Array<{
        connection_id: number;
        user: string;
        seconds: number;
        state: string;
        statement_type: string;
    }>;
    notes: string[];
}

export interface OperationsDashboard {
    health: OperationsHealthReport;
    performance: DatabasePerformanceReport;
    backup: {
        id: number;
        filename: string;
        size_bytes: number | null;
        verification_status: string;
        completed_at: string | null;
        verified_at: string | null;
    } | null;
    acceptance: {
        id: number;
        status: ProductionAcceptanceStatus;
        blocking_failures: number;
        completed_at: string | null;
    } | null;
    release_candidate: {
        id: number;
        version: string;
        status: ReleaseCandidateStatus;
        verification_status: ReleaseCandidateVerificationStatus;
        frozen_at: string | null;
        verified_at: string | null;
    } | null;
    queue: {
        queued: number;
        failed: number;
    };
    recent_operations_audit: Array<{
        id: number;
        event: string;
        actor_name: string | null;
        subject_label: string | null;
        created_at: string | null;
    }>;
}

export interface SystemBackupRow {
    id: number;
    filename: string;
    scope: string;
    initiated_by: string;
    size_bytes: number | null;
    checksum_sha256: string | null;
    status: string;
    verification_status: string;
    verification_message: string | null;
    started_at: string | null;
    completed_at: string | null;
    verified_at: string | null;
    pruned_at: string | null;
    requested_by: { id: number; name: string } | null;
    requested_tenant: { id: number; code: string; name: string } | null;
    can_verify: boolean;
}

export interface FailedJobRow {
    uuid: string;
    connection: string;
    queue: string;
    job: string;
    exception_class: string;
    failed_at: string;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
}

export interface DeploymentChecklistItem {
    key: string;
    label: string;
    owner: string;
}

export interface DeploymentPreflightReport {
    generated_at: string;
    environment: string;
    ready: boolean;
    production_readiness: {
        summary: {
            checks: number;
            passed: number;
            blocking_failures: number;
            warnings: number;
            ready: boolean;
        };
        checks: Array<{
            key: string;
            label: string;
            status: 'passed' | 'failed';
            blocking: boolean;
            message: string;
        }>;
    };
    operations_health: OperationsHealthReport;
    performance: DatabasePerformanceReport;
    security: {
        summary: { checks: number; critical_failures: number; warnings: number; secure: boolean };
        checks: Array<{ key: string; label: string; passed: boolean; critical: boolean; message: string }>;
    };
    cutover_checklist: DeploymentChecklistItem[];
    post_deployment_checklist: DeploymentChecklistItem[];
}

export type ProductionAcceptanceStatus = 'running' | 'passed' | 'blocked' | 'failed';
export type ProductionAcceptanceCheckStatus = 'passed' | 'warning' | 'failed';

export interface ProductionAcceptanceRunRow {
    id: number;
    uuid: string;
    status: ProductionAcceptanceStatus;
    environment: string;
    source: 'web' | 'cli';
    total_checks: number;
    passed_checks: number;
    warning_checks: number;
    failed_checks: number;
    blocking_failures: number;
    started_at: string | null;
    completed_at: string | null;
    started_by: { id: number; name: string } | null;
}

export interface ProductionAcceptanceCheck {
    id: number;
    sequence: number;
    category: string;
    key: string;
    label: string;
    status: ProductionAcceptanceCheckStatus;
    blocking: boolean;
    message: string;
    context: Record<string, unknown> | null;
    remediation: string[];
}

export interface ProductionAcceptanceReport {
    id: number;
    uuid: string;
    status: ProductionAcceptanceStatus;
    environment: string;
    source: 'web' | 'cli';
    project_fingerprint: string | null;
    summary: {
        checks: number;
        passed: number;
        warnings: number;
        failed: number;
        blocking_failures: number;
        ready: boolean;
    };
    started_by: { id: number; name: string } | null;
    started_at: string | null;
    completed_at: string | null;
    checks: ProductionAcceptanceCheck[];
}


export type ReleaseCandidateStatus = 'frozen' | 'superseded';
export type ReleaseCandidateVerificationStatus = 'matched' | 'drifted';

export interface ReleaseCandidateAcceptanceReference {
    id: number;
    uuid: string;
    status: ProductionAcceptanceStatus;
    blocking_failures: number;
    project_fingerprint?: string | null;
    completed_at: string | null;
}

export interface ReleaseCandidateRow {
    id: number;
    version: string;
    status: ReleaseCandidateStatus;
    environment: string;
    source: 'web' | 'cli';
    project_fingerprint: string;
    git_commit: string | null;
    verification_status: ReleaseCandidateVerificationStatus;
    frozen_at: string | null;
    verified_at: string | null;
    frozen_by: { id: number; name: string } | null;
    acceptance: ReleaseCandidateAcceptanceReference | null;
}

export interface ReleaseCandidateArtifact {
    key: string;
    label: string;
    sha256: string | null;
    metadata: Record<string, unknown> | null;
}

export interface ReleaseCandidateReport extends ReleaseCandidateRow {
    notes: string | null;
    superseded_at: string | null;
    verification_summary: {
        matched: boolean;
        current_fingerprint?: string;
        drifted_artifacts: Array<{
            key: string;
            label: string;
            frozen_sha256: string | null;
            current_sha256: string | null;
        }>;
    } | null;
    artifacts: ReleaseCandidateArtifact[];
}