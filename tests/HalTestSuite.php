<?php

namespace Tests;

use PHPUnit\Framework\TestSuite;

class HalTestSuite extends TestSuite {


    public static function suite() {
        $suite = new self();

        // Add GenerateHalModelsTest first
        $suite->addTestSuite(GenerateHalModelsTest::class);

        // Add other test classes
        $suite->addTestSuite(ModelCrudTest::class);
        $suite->addTestSuite(ModelRelationHalHasOneTest::class);
        $suite->addTestSuite(ModelRelationHalHasManyTest::class);
        // Add more test classes as needed

        return $suite;
    }
}
