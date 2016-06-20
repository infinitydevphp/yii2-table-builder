# Yii2 table generator class with migration template

- Install
```bash
php composer.phar require infinitydevphp/yii2-table-builder "1.0"
```

# Field config params
| Param name  | type |  Description | Required |
|-------------|:----:|:------------:|:--------:| 
| name  | string |  Field name in database | true |
| type | string |  One of definition type in yii\db\Schema | true |
| length | integer |  Length field for string numeric data | false |
| default | mixed | Default value | false |
| is_not_null | boolean | Is not null field | false |
| unsigned | boolean | Unsigned number | false |
| related_table | string | Related table name | false |
| related_field | string | Related field name | false |
| fk_name | string | Foreign key name | false |

# Usages
- Create table
```php
<?php 
use infinitydevphp\tableBuilder\TableBuilder;
use \yii\db\Schema;

$class = new TableBuilder([
    'tableName' => 'order',
    'fields' => [
        [
            'name' => 'id',
            'type' => Schema::TYPE_PK,
        ],
        [
            'name' => 'date_created',
            'type' => Schema::TYPE_TIMESTAMP,
        ],
        [
            'name' => 'price',
            'type' => Schema::TYPE_INTEGER,
        ],
        [
            'name' => 'good_id',
            'type' => Schema::TYPE_INTEGER,
        ],
        [
            'name' => 'user_id',
            'type' => Schema::TYPE_INTEGER,
            'length' => 11,
            'related_field' => 'user_id',
            'related_table' => 'order',
        ],
    ],
]);
$resultRelations = $class->runQuery();
```

- Create migration from template
```php
$class = new TableBuilderTemplateMigration([
    'tableName' => 'order'
]);
$migrationTemplateString = $class->runQuery();
```