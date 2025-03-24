<?php declare(strict_types=1);

namespace Cspray\ScorchedEarthGitConflictResolver;

enum ConflictType {
    case BothModified;
    case DeletedByUs;
    case DeletedByThem;
    case BothDeleted;

    public function isModified() : bool {
        return $this === self::BothModified;
    }

    public function isDeleted() : bool {
        return in_array($this, [self::DeletedByUs, self::DeletedByThem, self::BothDeleted], true);
    }
}
