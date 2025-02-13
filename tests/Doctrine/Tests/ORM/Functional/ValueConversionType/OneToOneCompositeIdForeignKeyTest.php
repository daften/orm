<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\ValueConversionType;

use Doctrine\Tests\Models;
use Doctrine\Tests\Models\ValueConversionType as Entity;
use Doctrine\Tests\OrmFunctionalTestCase;

/**
 * The entities all use a custom type that converst the value as identifier(s).
 * {@see \Doctrine\Tests\DbalTypes\Rot13Type}
 *
 * Test that OneToOne associations with composite id of which one is a
 * association itself work correctly.
 *
 * @group DDC-3380
 */
class OneToOneCompositeIdForeignKeyTest extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        $this->useModelSet('vct_onetoone_compositeid_foreignkey');
        parent::setUp();

        $auxiliary      = new Entity\AuxiliaryEntity();
        $auxiliary->id4 = 'abc';

        $inversed                = new Entity\InversedOneToOneCompositeIdForeignKeyEntity();
        $inversed->id1           = 'def';
        $inversed->foreignEntity = $auxiliary;
        $inversed->someProperty  = 'some value to be loaded';

        $owning      = new Entity\OwningOneToOneCompositeIdForeignKeyEntity();
        $owning->id2 = 'ghi';

        $inversed->associatedEntity = $owning;
        $owning->associatedEntity   = $inversed;

        $this->_em->persist($auxiliary);
        $this->_em->persist($inversed);
        $this->_em->persist($owning);

        $this->_em->flush();
        $this->_em->clear();
    }

    public static function tearDownAfterClass(): void
    {
        $conn = static::$sharedConn;

        $conn->executeStatement('DROP TABLE vct_owning_onetoone_compositeid_foreignkey');
        $conn->executeStatement('DROP TABLE vct_inversed_onetoone_compositeid_foreignkey');
        $conn->executeStatement('DROP TABLE vct_auxiliary');
    }

    public function testThatTheValueOfIdentifiersAreConvertedInTheDatabase(): void
    {
        $conn = $this->_em->getConnection();

        self::assertEquals('nop', $conn->fetchOne('SELECT id4 FROM vct_auxiliary LIMIT 1'));

        self::assertEquals('qrs', $conn->fetchOne('SELECT id1 FROM vct_inversed_onetoone_compositeid_foreignkey LIMIT 1'));
        self::assertEquals('nop', $conn->fetchOne('SELECT foreign_id FROM vct_inversed_onetoone_compositeid_foreignkey LIMIT 1'));

        self::assertEquals('tuv', $conn->fetchOne('SELECT id2 FROM vct_owning_onetoone_compositeid_foreignkey LIMIT 1'));
        self::assertEquals('qrs', $conn->fetchOne('SELECT associated_id FROM vct_owning_onetoone_compositeid_foreignkey LIMIT 1'));
        self::assertEquals('nop', $conn->fetchOne('SELECT associated_foreign_id FROM vct_owning_onetoone_compositeid_foreignkey LIMIT 1'));
    }

    /**
     * @depends testThatTheValueOfIdentifiersAreConvertedInTheDatabase
     */
    public function testThatEntitiesAreFetchedFromTheDatabase(): void
    {
        $auxiliary = $this->_em->find(
            Models\ValueConversionType\AuxiliaryEntity::class,
            'abc'
        );

        $inversed = $this->_em->find(
            Models\ValueConversionType\InversedOneToOneCompositeIdForeignKeyEntity::class,
            ['id1' => 'def', 'foreignEntity' => 'abc']
        );

        $owning = $this->_em->find(
            Models\ValueConversionType\OwningOneToOneCompositeIdForeignKeyEntity::class,
            'ghi'
        );

        self::assertInstanceOf(Models\ValueConversionType\AuxiliaryEntity::class, $auxiliary);
        self::assertInstanceOf(Models\ValueConversionType\InversedOneToOneCompositeIdForeignKeyEntity::class, $inversed);
        self::assertInstanceOf(Models\ValueConversionType\OwningOneToOneCompositeIdForeignKeyEntity::class, $owning);
    }

    /**
     * @depends testThatEntitiesAreFetchedFromTheDatabase
     */
    public function testThatTheValueOfIdentifiersAreConvertedBackAfterBeingFetchedFromTheDatabase(): void
    {
        $auxiliary = $this->_em->find(
            Models\ValueConversionType\AuxiliaryEntity::class,
            'abc'
        );

        $inversed = $this->_em->find(
            Models\ValueConversionType\InversedOneToOneCompositeIdForeignKeyEntity::class,
            ['id1' => 'def', 'foreignEntity' => 'abc']
        );

        $owning = $this->_em->find(
            Models\ValueConversionType\OwningOneToOneCompositeIdForeignKeyEntity::class,
            'ghi'
        );

        self::assertEquals('abc', $auxiliary->id4);
        self::assertEquals('def', $inversed->id1);
        self::assertEquals('abc', $inversed->foreignEntity->id4);
        self::assertEquals('ghi', $owning->id2);
    }

    /**
     * @depends testThatTheValueOfIdentifiersAreConvertedBackAfterBeingFetchedFromTheDatabase
     */
    public function testThatInversedEntityIsFetchedFromTheDatabaseUsingAuxiliaryEntityAsId(): void
    {
        $auxiliary = $this->_em->find(
            Models\ValueConversionType\AuxiliaryEntity::class,
            'abc'
        );

        $inversed = $this->_em->find(
            Models\ValueConversionType\InversedOneToOneCompositeIdForeignKeyEntity::class,
            ['id1' => 'def', 'foreignEntity' => $auxiliary]
        );

        self::assertInstanceOf(Models\ValueConversionType\InversedOneToOneCompositeIdForeignKeyEntity::class, $inversed);
    }

    /**
     * @depends testThatEntitiesAreFetchedFromTheDatabase
     */
    public function testThatTheProxyFromOwningToInversedIsLoaded(): void
    {
        $owning = $this->_em->find(
            Models\ValueConversionType\OwningOneToOneCompositeIdForeignKeyEntity::class,
            'ghi'
        );

        $inversedProxy = $owning->associatedEntity;

        self::assertEquals('some value to be loaded', $inversedProxy->someProperty);
    }

    /**
     * @depends testThatEntitiesAreFetchedFromTheDatabase
     */
    public function testThatTheEntityFromInversedToOwningIsEagerLoaded(): void
    {
        $inversed = $this->_em->find(
            Models\ValueConversionType\InversedOneToOneCompositeIdForeignKeyEntity::class,
            ['id1' => 'def', 'foreignEntity' => 'abc']
        );

        self::assertInstanceOf(Models\ValueConversionType\OwningOneToOneCompositeIdForeignKeyEntity::class, $inversed->associatedEntity);
    }
}
