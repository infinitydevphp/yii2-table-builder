<?php
namespace infinitydevphp\tableBuilder;


use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\Object;
use yii\db\ColumnSchemaBuilder;
use yii\db\Connection;
use yii\db\Migration;
use yii\db\Schema;
use Yii;

/**
 * Class TableBuilder
 * @property string $field - Config table fields
 * @property string $tableName - Table name
 * @property Connection|array|string $db the DB connection object or the application component ID of the DB connection
 *                                       that this migration should work with. Starting from version 2.0.2,
 *                                       this can also be a configuration array for creating the object.
 *
 * @package infinitydevphp\tableBuilder
 * @author infinitydevphp <infinitydevphp@gmail.com>
 */
class TableBuilder extends Object
{
    public $fields;
    public $tableName;
    /** @var array Columns for table generation */
    protected $columns = [];
    /** @var null|Migration $migrationClass Migration class instance for run creation table */
    protected $migrationClass = null;
    /** @var array|null Relation generate */
    protected $relations = [];
    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection
     * that this migration should work with. Starting from version 2.0.2, this can also be a configuration array
     * for creating the object.
     *
     * Note that when a Migration object is created by the `migrate` command, this property will be overwritten
     * by the command. If you do not want to use the DB connection provided by the command, you may override
     * the [[init()]] method like the following:
     *
     * ```php
     * public function init()
     * {
     *     $this->db = 'db2';
     *     parent::init();
     * }
     * ```
     */
    public $db = 'db';

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->migrationClass = new Migration([
            'db' => $this->db
        ]);
    }

    public function __construct($config)
    {
        parent::__construct($config);
        $this->generateFields();
    }

    protected function generateFields() {
        if (!is_array($this->fields) || !count($this->fields)) {
            $this->fields = [];
            $fieldsSchema = Yii::$app->db->schema->getTableSchema($this->tableName);
            if ($fieldsSchema->columns) {
                foreach ($fieldsSchema->columns as $_next) {
                    $this->fields[$_next->name] = [
                        'name' => $_next->name,
                        'length' => $_next->size,
                        'is_not_null' => $_next->allowNull,
                        'default_value' => $_next->defaultValue,
                        'type' => $_next->phpType,
                        'precision' => $_next->precision,
                        'unsigned' => $_next->unsigned,
                        'scale' => $_next->scale
                    ];
                }
                if (is_array($fieldsSchema->foreignKeys)) {
                    foreach ($fieldsSchema->foreignKeys as $foreignKey) {
                        var_dump($foreignKey);
                        $relation = [
                            'related_table' => $foreignKey[0],
                            'table_name' => $this->tableName
                        ];
                        foreach ($foreignKey as $_key => $_next) {
                            if (!is_numeric($_key)) {
                                $relation['related_field'] = $_next;
                                $relation['field'] = $_key;
                            }
                        }
                        $this->relations[] = $relation;
                    }
                }
            }
        }
    }

    /**
     * Create next row migration as ColumnSchemaBuilder
     * @param array $config
     * @return ColumnSchemaBuilder
     */
    protected function buildNextField($config)
    {
        /** @var ColumnSchemaBuilder $row */
        $row = null;
        $length = isset($config['length']) && $config['length'] ? $config['length'] : null;
        $precision = isset($config['precision']) && $config['length'] ? $config['precision'] : null;
        $scale = isset($config['scale']) && $config['length'] ? $config['scale'] : null;

        switch (isset($config['type']) && $config['type']) {
            case Schema::TYPE_BIGINT:
                $row = $this->migrationClass->bigInteger($length);
                break;
            case Schema::TYPE_PK:
                $row = $this->migrationClass->primaryKey($length);
                break;
            case Schema::TYPE_BIGPK:
                $row = $this->migrationClass->bigPrimaryKey($length);
                break;
            case Schema::TYPE_BINARY:
                $row = $this->migrationClass->binary($length);
                break;
            case Schema::TYPE_CHAR:
                $row = $this->migrationClass->char($length);
                break;
            case Schema::TYPE_TEXT:
                $row = $this->migrationClass->text();
                break;
            case Schema::TYPE_DATE:
                $row = $this->$this->migrationClass->date();
                break;
            case Schema::TYPE_DECIMAL:
                $row = $this->migrationClass->decimal($precision, $scale);
                break;
            case Schema::TYPE_DOUBLE:
                $row = $this->migrationClass->double($precision);
                break;
            case Schema::TYPE_FLOAT:
                $row = $this->migrationClass->float($precision);
                break;
            case Schema::TYPE_DATETIME:
                $row = $this->migrationClass->dateTime($precision);
                break;
            case Schema::TYPE_MONEY:
                $row = $this->migrationClass->money($precision, $scale);
                break;
            case Schema::TYPE_SMALLINT:
                $row = $this->migrationClass->smallInteger($length);
                break;
            case Schema::TYPE_TIME:
                $row = $this->migrationClass->time($precision);
                break;
            case Schema::TYPE_TIMESTAMP:
                $row = $this->migrationClass->timestamp($precision);
                break;
        }

        if ((isset($config['type']) && ($config['type'] == Schema::TYPE_UPK || $config['type'] == Schema::TYPE_UBIGPK)) ||
            (isset($config['unsigned']) && $config['unsigned'])
        ) {
            $row = $row->unsigned();
        }

        if (isset($config['default']) && !empty($config['default'])) {
            $row = $row->defaultValue($config['default']);
        }

        if (isset($config['is_not_null']) && $config['is_not_null']) {
            $row = $row->notNull();
        }

        if (isset($config['comment']) && $config['comment']) {
            $row = $row->comment($config['comment']);
        }

        if (isset($config['related_table']) && $config['related_table'] && isset($config['related_field']) && $config['related_field']) {
            $this->relations[] = [
                'fk_name' => isset($config['fk_name']) && $config['fk_name'] ? $config['fk_name'] : '',
                'table_name' => $this->tableName,
                'field' => $config['name'],
                'related_table' => $config['related_table'],
                'related_field' => $config['related_field']
            ];
        }

        return $row;
    }

    /**
     * Build all table rows from config
     */
    public function buildQuery()
    {
        foreach ($this->fields as $_nextRow) {
            $this->columns[$_nextRow['name']] = $this->buildNextField($_nextRow);
        }
    }

    /**
     * Prepare fix string length
     * @param string $str Source string
     * @param int $countSymb Count symbol substring from begin
     * @return string
     */
    protected static function prepStr($str, $countSymb = 10)
    {
        $str = str_replace(['-', '_'], ['', ''], $str);
        $len = strlen($str);
        $len = $len > $countSymb ? $countSymb : $len;
        return substr($str, 0, $len);
    }

    /**
     * Auto generate next foreign key name
     * @param string $tableName Source table name
     * @param string $tableNameRelated Related table name
     * @param string $fieldName Field name source
     * @param string $fieldNameRelated related field name
     * @return string
     */
    public static function getNameForeignKey($tableName, $tableNameRelated, $fieldName, $fieldNameRelated)
    {
        return 'fk_' . self::prepStr($tableName) . '_' . self::prepStr($fieldName) . '_' . self::prepStr($tableNameRelated) . '_' . self::prepStr($fieldNameRelated);
    }

    /**
     * Relate creations
     * @return bool
     */
    public function runRelations()
    {
        $result = true;
        foreach ($this->relations as $_nextRelation) {
            $keyName = isset($_nextRelation['fk_name']) ?
                $_nextRelation['fk_name'] :
                self::getNameForeignKey($_nextRelation['table_name'], $_nextRelation['related_table'], $_nextRelation['field'], $_nextRelation['related_field']);
            $result=  $this->migrationClass->addForeignKey($keyName, $_nextRelation[0], $_nextRelation[2], $_nextRelation[1], $_nextRelation[3], 'RESTRICT', 'CASCADE') && $result;
        }

        return $result;
    }

    /**
     * Create table in database
     */
    public function runCreateTable()
    {
        $this->migrationClass->createTable($this->tableName, $this->columns);
    }

    public function runQuery($build = true)
    {
        if ($build)
            $this->buildQuery();

        $this->runCreateTable();
        return $this->runRelations();
    }
}