<?php echo "<?php"; ?>
use yii\db\Schema;
use yii\db\Migration;

class <?php echo $classname; ?> extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('<?php echo $tablename; ?>', [
    <?php
    foreach ($fields as $_name => $_nextField) { ?>
        <?php echo "'{$_name}' => {$_nextField}," . PHP_EOL;?>
    <?}?>

        ], $tableOptions);


    <?php
    if (is_array($foreignKey)) {
        foreach ($foreignKey as $_nextKey) {?>
    $this->addForeignKey('<?= $_nextKey['fk_name'] ;?>', '<?=$_nextKey['table_name'];?>', '<?=$_nextKey['field'];?>', '<?=$_nextKey['related_table'];?>', '<?=$_nextKey['related_field'];?>', <?= $_nextKey['delete'] ? "'{$_nextKey['delete']}'" : 'null'?>, <?= $_nextKey['update'] ? "'{$_nextKey['update']}'" : 'null' ?>);<?php echo PHP_EOL; ?>
<?      }
    }?>
    }

    public function down()
    {
    <?php
    if (is_array($foreignKey)) {
foreach ($foreignKey as $_nextKey) {?>
    $this->dropForeignKey('<?= $_nextKey['fk_name'] ;?>', '<?=$_nextKey['table_name'];?>');
<?}}?>
        $this->dropTable('<?php echo $tablename; ?>');
    }
}