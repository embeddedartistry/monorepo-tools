<?php

namespace Tests\ShopBundle\Unit\Component\Domain\Multidomain;

use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit_Framework_TestCase;
use Shopsys\ShopBundle\Component\Domain\Multidomain\MultidomainEntityClassFinder;

class MultidomainEntityClassFinderTest extends PHPUnit_Framework_TestCase
{
    public function testGetMultidomainEntitiesNames()
    {
        $classMetadataMock1 = $this->createMock(ClassMetadata::class);
        $classMetadataMock1
            ->method('getIdentifierFieldNames')
            ->willReturn(['id', 'testId']);
        $classMetadataMock1
            ->method('getName')
            ->willReturn('NonMultidomainClass1');

        $classMetadataMock2 = $this->createMock(ClassMetadata::class);
        $classMetadataMock2
            ->method('getIdentifierFieldNames')
            ->willReturn(['domainId']);
        $classMetadataMock2
            ->method('getName')
            ->willReturn('NonMultidomainClass2');

        $classMetadataMock3 = $this->createMock(ClassMetadata::class);
        $classMetadataMock3
            ->method('getIdentifierFieldNames')
            ->willReturn(['id', 'domainId']);
        $classMetadataMock3
            ->method('getName')
            ->willReturn('MultidomainClass');
        $allClassesMetadata = [$classMetadataMock1, $classMetadataMock2, $classMetadataMock3];

        $multidomainEntityClassFinder = new MultidomainEntityClassFinder();
        $multidomainEntitiesNames = $multidomainEntityClassFinder->getMultidomainEntitiesNames($allClassesMetadata, [], []);

        $this->assertSame(['MultidomainClass'], $multidomainEntitiesNames);
    }
}