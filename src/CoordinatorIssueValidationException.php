<?php
namespace LBDistrictScouts\DistrictWordpressPlugin;

use RuntimeException;

class CoordinatorIssueValidationException extends RuntimeException {
    private array $validation_errors;

    public function __construct( array $validation_errors ) {
        $this->validation_errors = $validation_errors;
        parent::__construct( implode( ' ', $validation_errors ) );
    }

    public function get_validation_errors(): array {
        return $this->validation_errors;
    }
}
