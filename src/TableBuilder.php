<?php
namespace infinitydevphp\tableBuilder;


use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\Object;
use yii\db\ColumnSchemaBuilder;
use yii\db\Connection;
use yii\db\IntegrityException;
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
     * @var string the DB connection components name
     */
    public $db = 'db';
    /** @var Connection */
    protected $_connection = null;
    public $dropOriginTable = false;
    public $tableNameRaw;
    public $hideMigrationOutput = true;
    public $useTablePrefix = false;
    public $primaryKeys = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->migrationClass = new Migration([
            'db' => $this->db
        ]);

        $this->_connection = Yii::$app->{$this->db};
    }

    public function afterInit()
    {
    }

    public function __construct($config)
    {
        parent::__construct($config);
        $this->tableNameRaw = Yii::$app->db->schema->getRawTableName($this->tableName);

        if (!$this->tableName) {
            throw new ErrorException("Table name not defined");
        }
        $this->afterInit();
        $this->generateFields();
    }

    protected function generateFields()
    {
        if (!is_array($this->fields) || !count($this->fields)) {
            $this->fields = [];
            $fieldsSchema = $this->_connection->schema->getTableSchema($this->tableNameRaw);
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
                        $relation = [
                            'related_table' => $foreignKey[0],
                            'table_name' => $this->tableNameRaw
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

        $this->migrationClass = new Migration([
            'db' => $this->db
        ]);

        if (!(isset($config['type']) && $config['type']))
            throw new \ErrorException("Type field is undefined");

        switch ($config['type']) {
            case Schema::TYPE_PK:
                $row = $this->migrationClass->primaryKey($length);
                break;
            case Schema::TYPE_UPK:
                $row = $this->migrationClass->primaryKey($length);
                break;
            case Schema::TYPE_INTEGER:
                $row = $this->migrationClass->integer($length);
                break;
            case Schema::TYPE_BIGINT:
                $row = $this->migrationClass->bigInteger($length);
                break;
            case Schema::TYPE_BIGPK:
                $row = $this->migrationClass->bigPrimaryKey($length);
                break;
            case Schema::TYPE_UBIGPK:
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
            default:
                $row = $this->migrationClass->string($length);
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

        if (isset($config['isCompositeKey']) && $config['isCompositeKey']) {
            $this->primaryKeys[] = $config['name'];
        }

        $config['related_table'] = isset($config['related_table']) ? trim($config['related_table']) : null;
        $config['related_field'] = isset($config['related_field']) ? trim($config['related_field']) : null;

        if ($config['related_table'] && $config['related_field']) {
            $this->relations[] = [
                'fk_name' => isset($config['fk_name']) && $config['fk_name'] ? $config['fk_name'] :
                    self::getNameForeignKey($this->tableNameRaw, $config['related_table'], $config['name'], $config['related_field'], 255),
                'table_name' => $this->addTablePrefix($this->tableNameRaw),
                'field' => $config['name'],
                'related_table' => $this->addTablePrefix($config['related_table']),
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
    protected static function prepStr($str, $countSymb = 255)
    {
        //$str = str_replace(['-', '_'], ['', ''], $str);
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
    public static function getNameForeignKey($tableName, $tableNameRelated, $fieldName, $fieldNameRelated, $wordWrap = false)
    {
        return is_bool($wordWrap) ? 'fk_' . self::prepStr($tableName) . '_' . self::prepStr($fieldName) . '_' . self::prepStr($tableNameRelated) . '_' . self::prepStr($fieldNameRelated)
            : self::prepStr('fk_' . $tableName . '_' . $fieldName . '_' . $tableNameRelated . '_' . $fieldNameRelated, $wordWrap);
    }

    /**
     * Relate creations
     * @return bool
     */
    public function runRelations()
    {
        $result = true;
        foreach ($this->relations as $_nextRelation) {
            try {
                if ($this->hideMigrationOutput) {
                    ob_start();
                }
                $result = $this->migrationClass->addForeignKey($_nextRelation['fk_name'],
                        $_nextRelation['table_name'],
                        $_nextRelation['field'],
                        $_nextRelation['related_table'],
                        $_nextRelation['related_field'], 'RESTRICT', 'CASCADE') && $result;

                if ($this->hideMigrationOutput) {
                    ob_clean();
                    ob_flush();
                }
            } catch (\yii\db\Exception $expt) {
            }
        }

        return $result;
    }

    public function dropTable()
    {
        if ($this->dropOriginTable) {
            if (in_array($this->tableNameRaw, $this->_connection->schema->tableNames)) {
                $schema = $this->_connection->schema->getTableSchema($this->tableNameRaw);
                if ($schema) {
                    $keys = $schema->foreignKeys;
                    if (is_array($keys)) {
                        foreach ($keys as $name => $_next) {

                            $t = [];
                            foreach ($_next as $k => $v) {
                                if (!$k) {
                                    $t['related_table'] = $v;
                                } else {
                                    $t['related_field'] = $v;
                                    $t['field'] = $k;
                                }
                            }

                            try {
                                $keyName = $this->getNameForeignKey($this->tableNameRaw, $t['related_table'], $_next[$t['field']], $t['related_field'], 255);
                                if ($this->hideMigrationOutput) {
                                    ob_start();
                                }
                                $this->migrationClass->dropForeignKey($keyName, $this->tableNameRaw);
                                if ($this->hideMigrationOutput) {
                                    ob_clean();
                                    ob_flush();
                                }
                            } catch (\yii\db\Exception $exp) {
                            }
                        }
                    }
                    if ($this->hideMigrationOutput) {
                        ob_start();
                    }
                    try {
                        $this->migrationClass->dropTable($this->tableNameRaw);
                    } catch (\yii\db\Exception $exp) {

                    } catch (IntegrityException $exp) {

                    }

                    if ($this->hideMigrationOutput) {
                        ob_clean();
                        ob_flush();
                    }
                }
            }
        }
    }

    /**
     * Create table in database
     */
    public function runCreateTable()
    {
        $this->dropTable();
        if ($this->hideMigrationOutput) {
            ob_start();
        }
        /** @var Connection $_conn */
        $_conn = Yii::$app->{$this->db};
        if (!$_conn->schema->getTableSchema($this->tableName)) {
            $this->migrationClass->createTable($this->tableNameRaw, $this->columns);

            if (is_array($this->primaryKeys) && sizeof($this->primaryKeys)) {
                try {
                    $this->migrationClass->addPrimaryKey("{$this->tableNameRaw}_pk", $this->tableNameRaw, $this->primaryKeys);
                } catch (\yii\db\Exception $exp) {}
            }
        }
        if ($this->hideMigrationOutput) {
            ob_clean();
            ob_flush();
        }
    }

    protected function addTablePrefix($tableName) {
        if (!$this->useTablePrefix) {
            $tableName = str_replace(['{{%', '}}'], '', $tableName);

            return $tableName;
        }

        return substr_count($tableName, '{{%') ? $tableName : "{{%$tableName}}";
    }

    protected function generatePrimaryKey() {
        if (is_array($this->primaryKeys) && !count($this->primaryKeys)) {
            $db = $this->db;
            /** @var Connection $db */
            $db = Yii::$app->{$db};
            if ($tableSchema = $db->getTableSchema($this->tableNameRaw)) {
                $this->primaryKeys = $tableSchema->primaryKey;
            }
        }
    }

    public function runQuery($build = true)
    {
        if ($build)
            $this->buildQuery();

        $this->generatePrimaryKey();
        $this->runCreateTable();
        return $this->runRelations();
    }
}