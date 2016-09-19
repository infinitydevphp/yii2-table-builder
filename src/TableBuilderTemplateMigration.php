<?php
/**
 * Created by IntelliJ IDEA.
 * User: infinitydevphp
 * Date: 08/06/16
 * Time: 14:48
 */

namespace infinitydevphp\tableBuilder;

use yii\base\ErrorException;
use yii\base\Exception;
use yii\db\ColumnSchemaBuilder;
use yii\db\Connection;
use yii\db\Expression;
use yii\db\Migration;
use yii\db\Query;
use yii\db\Schema;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\View;

/**
 * Class TableBuilderTemplateMigration
 * @property string $field - Config table fields
 * @property string $tableName - Table name
 * @property Connection|array|string $db the DB connection object or the application component ID of the DB connection
 *                                       that this migration should work with. Starting from version 2.0.2,
 *                                       this can also be a configuration array for creating the object.
 * @property string $migrationTemplate path to migration template view
 * @property string $prefix addition prefix to auto generate migration name
 * @package infinitydevphp\tableBuilder
 * @author infinitydevphp <infinitydevphp@gmail.com>
 */
class TableBuilderTemplateMigration extends TableBuilder
{
    public $migrationName = '';
    public $prefix = 'create_table_';
    public $migrationTemplate = '';

    public function afterInit() {
        $this->migrationName = $this->getMigrationName($this->migrationName);
        $this->migrationTemplate = __DIR__ . '/MigrationTemplate.php';
    }

    protected function getDefaultValue($val) {
        if (is_bool($val)) {
            return $val ? 1 : 0;
        }

        if (is_numeric($val)) {
            return $val;
        }

        return '"' . str_replace('"', "'", $val) . '"';
    }

    /**
     * @inheritdoc
     */
    protected function buildNextField($config) {
        $pksType = [Schema::TYPE_PK, Schema::TYPE_UPK, Schema::TYPE_BIGPK];
        /** @var ColumnSchemaBuilder $row */

        $row = null;
        $length = isset($config['length']) && $config['length'] ? $config['length'] : null;
        $precision = isset($config['precision']) && $config['length'] ? $config['precision'] : null;
        $scale = isset($config['scale']) && $config['length'] ? $config['scale'] : null;

        if (!(isset($config['type']) && $config['type']))
            throw new \ErrorException("Type field is undefined");

        switch ($config['type']) {
            case Schema::TYPE_BIGINT:
                $this->primaryKeys[] = $config['name'];
                $row .= "\$this->bigInteger({$length})";
                break;
            case Schema::TYPE_INTEGER:
                $row = "\$this->integer({$length})";
                break;
            case Schema::TYPE_PK:
                $this->primaryKeys[] = $config['name'];
                $row = "\$this->primaryKey({$length})";
                break;
            case Schema::TYPE_UPK:
                $row = "\$this->primaryKey({$length})";
                break;
            case Schema::TYPE_BIGPK:
                $this->primaryKeys[] = $config['name'];
                $row = "\$this->bigPrimaryKey({$length})";
                break;
            case Schema::TYPE_UBIGPK:
                $this->primaryKeys[] = $config['name'];
                $row = "\$this->bigPrimaryKey({$length})";
                break;
            case Schema::TYPE_BINARY:
                $row = "\$this->binary({$length})";
                break;
            case Schema::TYPE_CHAR:
                $row = "\$this->char({$length})";
                break;
            case Schema::TYPE_TEXT:
                $row = "\$this->text()";
                break;
            case Schema::TYPE_DATE:
                $row = "\$this->date()";
                break;
            case Schema::TYPE_DECIMAL:
                $row = "\$this->decimal({$precision}, {$scale})";
                break;
            case Schema::TYPE_DOUBLE:
                $row = "\$this->double({$precision})";
                break;
            case Schema::TYPE_FLOAT:
                $row = "\$this->float({$precision})";
                break;
            case Schema::TYPE_DATETIME:
                $row = "\$this->dateTime({$precision})";
                break;
            case Schema::TYPE_MONEY:
                $row = "\$this->money({$precision}, {$scale})";
                break;
            case Schema::TYPE_SMALLINT:
                $row = "\$this->smallInteger({$length})";
                break;
            case Schema::TYPE_TIME:
                $row = "\$this->time({$precision})";
                break;
            case Schema::TYPE_TIMESTAMP:
                $row = "\$this->timestamp({$precision})";
                break;
            default:
                $row = "\$this->string({$length})";
                break;
        }

        if (isset($config['isCompositeKey']) && $config['isCompositeKey']) {
            $this->primaryKeys[] = $config['name'];
        }

        if ((isset($config['type']) && ($config['type'] == Schema::TYPE_UPK || $config['type'] == Schema::TYPE_UBIGPK)) ||
            (isset($config['unsigned']) && $config['unsigned'])) {
            $row .= "->unsigned()";
        }

        if (isset($config['default']) && !empty($config['default'])) {
            $row .= "->defaultValue(" . $this->getDefaultValue($config['default']) . ")";
        }

        if (isset($config['is_not_null']) && $config['is_not_null']) {
            $row .= "->notNull()";
        }

        if (isset($config['comment']) && $config['comment']) {
            $row .= "->comment(\"" . str_replace('"', "'", $config['comment']) . '")';
        }

        if (isset($config['related_table']) && $config['related_table'] && isset($config['related_field']) && $config['related_field']) {
            $this->relations[] = [
                'fk_name' => isset($config['fk_name']) && $config['fk_name'] ? $config['fk_name'] :
                    $this->getNameForeignKey($this->tableNameRaw, $config['related_table'], $config['name'], $config['related_field'], 255),
                'table_name' => $this->addTablePrefix($this->tableNameRaw),
                'field' => $config['name'],
                'related_table' => $this->addTablePrefix($config['related_table']),
                'related_field' => $config['related_field']
            ];
        }

        return $row;
    }

    protected $allRelations = [];

    protected function findRelationName($tableName, $relationConfig) {
        if ($this->_connection->driverName === 'mysql') {
            $foreignKey = new Query();

            if (!isset($this->allRelations[$tableName])) {
                $this->allRelations[$tableName] = $foreignKey->select([
                    'i.TABLE_NAME as table_name',
                    'i.CONSTRAINT_TYPE as constraint_type',
                    'i.CONSTRAINT_NAME as constraint_name',
                    'k.REFERENCED_TABLE_NAME as referenced_table_name',
                    'k.REFERENCED_COLUMN_NAME as referenced_column_name',
                    'k.COLUMN_NAME as column_name'
                ])->from('information_schema.TABLE_CONSTRAINTS as i')
                                        ->innerJoin('information_schema.KEY_COLUMN_USAGE as k', '`i`.`CONSTRAINT_NAME` = `k`.`CONSTRAINT_NAME`')->andWhere([
                        'i.CONSTRAINT_TYPE' => 'FOREIGN KEY',
                        'i.TABLE_SCHEMA'    => new Expression('DATABASE()'),
                        'i.TABLE_NAME'      => $this->_connection->schema->getRawTableName($tableName),
                    ])->all($this->_connection);
            }

            foreach ($this->allRelations[$tableName] as $item) {
                if ($item['referenced_column_name'] == $relationConfig['related_field'] && $item['column_name'] == $relationConfig['field']) {
                    return $item['constraint_name'];
                }
            }
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function runRelations() {
        $view = new View();

        foreach ($this->relations as $index => $relation) {
            $this->relations[$index]['fk_name'] = isset($relation['fk_name']) ? $relation['fk_name'] : $this->findRelationName($this->tableName, $relation);
        }

        return $view->renderFile($this->migrationTemplate, [
            'tableName' => $this->addTablePrefix($this->tableName),
            'tableNameRaw' => $this->tableNameRaw,
            'fields' => $this->columns,
            'classname' => $this->getMigrationName($this->prefix . $this->tableName),
            'foreignKey' => $this->relations ? : [],
            'db' => $this->db,
            'fieldNames' => ArrayHelper::map($this->fields, 'name', 'name'),
            'primaryKeys' => $this->primaryKeys,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function runCreateTable() {}

    /**
     * Save migration name in session
     * @param string $name Migration name
     * @return mixed
     */
    protected function saveTblNameInSession($name) {
        if ($session = Yii::$app->getComponents()) {
            if (isset($session['session'])) {
                if ($mName = Yii::$app->session->get($this->tableName)) {
                    return $mName;
                }

                Yii::$app->session->set($this->tableName, $name);

                return $name;
            }
        }

        return $name;
    }

    /**
     * Reset migration name in session
     */
    public function resetSessionMigrationName() {
        if ($session = Yii::$app->getComponents()) {
            if (isset($session['session'])) {
                Yii::$app->session->set($this->tableName, null);
            }
        }
    }

    /**
     * Auto generate migration name
     * @param string $name Migration name
     * @return mixed|string
     */
    public function getMigrationName($name = '') {
        $name = $name ? : $this->prefix . $this->tableNameRaw;

        $components = Yii::$app->getComponents();
        if (isset($components['session'])) {
            Yii::$app->session->set($this->tableName, '');
        }
        $this->migrationName= $this->migrationName ? : 'm' . gmdate('ymd_His') . '_' . $name;

        $name = $this->saveTblNameInSession($this->migrationName);

        return $name;
    }
}