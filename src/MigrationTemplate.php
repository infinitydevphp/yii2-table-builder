<?php
if (!function_exists('duplicate')) {
    function duplicate($name, $index, $keys)
    {
        foreach ($keys as $_index => $_name) {
            if ($index !== $_index && $name == $_name) {
                return true;
            }
        }

        return false;
    }
}
?>
<?php echo "<?php" . PHP_EOL; ?>
use yii\db\Schema;
use yii\db\Migration;

class <?php echo $classname; ?> extends Migration
{
    public $db = '<?php echo $db; ?>';
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('<?php echo $tablename; ?>', [
<?php
foreach ($fields as $_name => $_nextField) { ?>
            '<?php echo $_name;?>' => <?php echo $_nextField . "," . PHP_EOL; ?>
<?php }?>

        ], $tableOptions);

<?php
if (is_array($foreignKey)) {
foreach ($foreignKey as $_nextKey) {?>
        $this->addForeignKey('<?= $_nextKey['fk_name'] ;?>',
                             '<?=$_nextKey['table_name'];?>',
                             '<?=$_nextKey['field'];?>',
                             '<?=$_nextKey['related_table'];?>',
                             '<?=$_nextKey['related_field'];?>',
                             <?= $_nextKey['delete'] ? "'{$_nextKey['delete']}'" : 'null'?>,
                             <?= $_nextKey['update'] ? "'{$_nextKey['update']}'" : 'null' ?>);
<?php echo PHP_EOL; ?>
<?php }}?>
    }

    public function down()
    {
<?php
if (is_array($foreignKey)) {

foreach ($foreignKey as $index => $_nextKey) { if (!duplicate($_nextKey['fk_name'], $index, $foreignKey)) {?>
        <?php echo "\$this->dropForeignKey('{$_nextKey['fk_name']}', '{$_nextKey['table_name']}');" . PHP_EOL; ?>
<?php }}}?>
        $this->dropTable('<?php echo $tablename; ?>');
    }
}