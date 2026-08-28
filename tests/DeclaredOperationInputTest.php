<?php

use Splicewire\Beam\Particle\Attributes\ParticleOp;

/**
 * **Every `#[ParticleOp]` this package declares carries an explicit `input:`.**
 *
 * ⚠️ This is a PACKAGE-LOCAL, STATIC guard, and both halves are the point.
 *
 * `Splicewire\Beam\Doctor\UndeclaredInputAudit` — the estate's instrument for this — reads the
 * BOOTED `ParticleOperationRegistry`, which is the right instrument in a host and blind here:
 * measured 2026-08-28, this package's own testbench registers **0** particle operations, so the
 * audit run from inside it reports a green PASS over an empty population. A false green is worse
 * than no check, which is why api-surface-coherence 121 refused to twin the audit statically inside
 * beam and put the guard where the declarations actually live instead. Beam's own `AuditScanPaths`
 * already names this fallback: the boot-time seam "is not a fleet-wide census", and a package not
 * installed in the host under audit is covered "by its own package-local tooling".
 *
 * Scope is deliberately this package's own source and nothing else — a guard, never a census.
 *
 * The three legal answers are a Data class-string, `false` ("accepts nothing", which the controller
 * then ENFORCES by rejecting a body), and — for an operation whose input is genuinely host-bound —
 * an entry in `UndeclaredInputAudit::ACKNOWLEDGED`. `null` is none of them: it is the undeclared
 * state, and it reads as unfinished work rather than as a decision.
 */
it('declares an explicit `input:` on every particle operation it ships', function () {
    $undeclared = [];

    foreach (opClassesInThisPackage() as $class) {
        foreach ((new ReflectionClass($class))->getAttributes(ParticleOp::class) as $attribute) {
            if ($attribute->newInstance()->input === null) {
                $undeclared[] = $class;
            }
        }
    }

    expect($undeclared)->toBe([], 'Undeclared `input:` on: '.implode(', ', $undeclared));
});

/**
 * @return list<class-string>
 */
function opClassesInThisPackage(): array
{
    $root = dirname(__DIR__, 1).'/src';
    $classes = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        // Cheap pre-filter: reflecting every class in the package would autoload the world for the
        // sake of a handful of attributes.
        if (! str_contains($source, '#[ParticleOp(')) {
            continue;
        }

        if (! preg_match('/^namespace\s+([^;]+);/m', $source, $ns)
            || ! preg_match('/^class\s+(\w+)/m', $source, $name)) {
            continue;
        }

        $class = trim($ns[1]).'\\'.$name[1];

        if (class_exists($class)) {
            $classes[] = $class;
        }
    }

    sort($classes);

    // A guard that silently finds nothing is the failure mode it exists to prevent.
    expect($classes)->not->toBeEmpty();

    return $classes;
}
