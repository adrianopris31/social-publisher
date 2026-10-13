<?php

declare(strict_types=1);

/**
 * Overrides only; Boost merges its package defaults for every other key.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Agent Overrides
    |--------------------------------------------------------------------------
    |
    | CLAUDE.md only imports AGENTS.md and the project docs, so Boost must
    | write Claude Code's guidelines to AGENTS.md instead of CLAUDE.md.
    |
    */

    'agents' => [
        'claude_code' => [
            'guidelines_path' => 'AGENTS.md',
        ],
    ],

];
