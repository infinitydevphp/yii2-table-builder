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
use yii\db\Migration;
use yii\db\Schema;
use Yii;
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
        /** @var ColumnSchemaBuilder $row */
        $row = null;
        $length = isset($config['length']) && $config['length'] ? $config['length'] : null;
        $precision = isset($config['precision']) && $config['length'] ? $config['precision'] : null;
        $scale = isset($config['scale']) && $config['length'] ? $config['scale'] : null;

        if (!(isset($config['type']) && $config['type']))
            throw new \ErrorException("Type field is undefined");

        switch ($config['type']) {
            case Schema::TYPE_BIGINT:
                $row .= "\$this->bigInteger({$length})";
                break;
            case Schema::TYPE_INTEGER:
                $row = "\$this->integer({$length})";
                break;
            case Schema::TYPE_PK:
                $row = "\$this->primaryKey({$length})";
                break;
            case Schema::TYPE_UPK:
                $row = "\$this->primaryKey({$length})";
                break;
            case Schema::TYPE_BIGPK:
                $row = "\$this->bigPrimaryKey({$length})";
                break;
            case Schema::TYPE_UBIGPK:
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

    /**
     * @inheritdoc
     */
    public function runRelations() {
        $view = new View();
        return $view->renderFile($this->migrationTemplate, [
            'tablename' => $this->addTablePrefix($this->tableName),
            'fields' => $this->columns,
            'classname' => $this->getMigrationName($this->prefix . $this->tableName),
            'foreignKey' => $this->relations,
            'db' => $this->db
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
        if ($session = Yii::$app->getComponents('session')) {
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
        if ($session = Yii::$app->getComponents('session')) {
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
    protected function getMigrationName($name = '') {
        $name = $name ? : $this->prefix . $this->tableNameRaw;
        Yii::$app->session->set($this->tableName, '');
        $this->migrationName= $this->migrationName ? : 'm' . gmdate('ymd_His') . '_' . $name;

        $name = $this->saveTblNameInSession($this->migrationName);

        return $name;
    }
}