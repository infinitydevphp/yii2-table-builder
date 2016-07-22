<?php


require __DIR__ . "/../../../autoload.php";

use infinitydevphp\gii\models\Field;
use PHPUnit\Framework\TestCase;
use infinitydevphp\tableBuilder\TableBuilder;
use yii\db\Schema;


/**
 * @class MigrateTest
 *
 * @package infinitydevphp\gii\test
 * @author Sergey Doniy <doniysa@gmail.com>
 */
class MigrateTest extends TestCase
{
    public function testInitialConfig() {
        $tableBuilder = new TableBuilder([
            'tableName' => '{{%user}}',
            'fields' => [
                ['name' => 'id', 'type' => Schema::TYPE_PK]
            ],
        ]);

        $this->assertEquals($tableBuilder->tableNameRaw, 'user');
    }
}