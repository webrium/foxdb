<?php
namespace Foxdb;

use Foxdb\DB;

class Schema
{
    private string $table;
    private array $fields = [];
    private string $change_action = '';
    private string $change_position = '';

    const INDEX_UNIQUE = 'UNIQUE';
    const INDEX_PRIMARY = 'PRIMARY';
    const INDEX = 'INDEX';

    /**
     * Constructor method to set the table name.
     *
     * @param string $table
     */
    public function __construct($table)
    {
        $this->table = $table;
    }


    private function reset()
    {
        $this->fields = [];
        $this->change_action = '';
        $this->change_position = '';
    }

    /**
     * Add an auto-incrementing primary key column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function id($name = 'id')
    {
        $this->increments($name);
        return $this;
    }

    /**
     * Add an auto-incrementing primary key column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function bigID($name = 'id')
    {
        return $this->increments($name, 'BIGINT(20)');
    }

    /**
     * Add an auto-incrementing primary key column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function increments($name = 'id', $type = 'INT(11)')
    {
        $this->fields[] = "`$name` $type UNSIGNED AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }

    /**
     * Add an integer column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function integer($name, $length = 11)
    {
        $this->fields[] = "`$name` INT($length)";
        return $this;
    }

    /**
     * Add an big integer column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function bigInt($name, $length = 20)
    {
        $this->fields[] = "`$name` BIGINT($length)";
        return $this;
    }

    /**
     * Add a string column to the table.
     *
     * @param string $name
     * @param int $length
     * @return $this
     */
    public function string($name, $length = 255)
    {
        $this->fields[] = "`$name` VARCHAR($length)";
        return $this;
    }

    /**
     * Add a text column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function text($name)
    {
        $this->fields[] = "`$name` TEXT";
        return $this;
    }

    /**
     * Add a long text column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function longText($name)
    {
        $this->fields[] = "`$name` LONGTEXT";
        return $this;
    }

    /**
     * Add a JSON column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function json($name)
    {
        $this->fields[] = "`$name` JSON";
        return $this;
    }

    /**
     * Add created_at and updated_at timestamp columns to the table.
     *
     * @return $this
     */
    public function timestamps()
    {
        $this->fields[] = "`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        $this->fields[] = "`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        return $this;
    }


    /**
     * Add a dateTime column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function dateTime($name)
    {
        $this->fields[] = "`$name` DATETIME";
        return $this;
    }


    /**
     * Set a default value for the last column added to the table.
     *
     * @param string $value
     * @return $this
     */
    public function default($value)
    {
        $this->fields[count($this->fields) - 1] .= " DEFAULT '$value'";
        return $this;
    }

    /**
     * Set the last column added to the table as nullable.
     *
     * @return $this
     */
    public function nullable()
    {
        $this->fields[count($this->fields) - 1] .= " NULL";
        return $this;
    }

    /**
     * Add a time column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function time($name)
    {
        $this->fields[] = "`$name` TIME";
        return $this;
    }

    /**
     * Add a date column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function date($name)
    {
        $this->fields[] = "`$name` DATE";
        return $this;
    }

    /**
     * Add a float column to the table.
     *
     * @param string $name
     * @param string $length
     * @return $this
     */
    public function float($name, $length = '10,2')
    {
        $this->fields[] = "`$name` FLOAT($length)";
        return $this;
    }

    /**
     * Add a year column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function year($name)
    {
        $this->fields[] = "`$name` YEAR(4)";
        return $this;
    }


    /**
     * Add a double column to the table.
     *
     * @param string $name
     * @param string $length
     * @return $this
     */
    public function double($name, $length = '10,2')
    {
        $this->fields[] = "`$name` DOUBLE($length)";
        return $this;
    }

    /**
     * Add an enum column to the table.
     *
     * @param string $name
     * @param array $values
     * @return $this
     */
    public function enum($name, array $values)
    {
        $this->fields[] = "`$name` ENUM('" . implode("','", $values) . "')";
        return $this;
    }

    /**
     * Adds a boolean field to the table.
     *
     * @param  string  $name
     * @return $this
     */
    public function boolean($name)
    {
        $this->fields[] = "`$name` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0'";
        return $this;
    }


    /**
     * Generate a CREATE TABLE query for the current table and fields.
     *
     * @return string
     */
    public function create($engine = 'InnoDB', $charset = 'utf8mb4', $collate = 'utf8mb4_unicode_ci')
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (";
        $sql .= implode(', ', $this->fields);
        $sql .= ") ENGINE={$engine} DEFAULT CHARSET={$charset} COLLATE={$collate};";

        $this->reset();

        return DB::query($sql);
        ;
    }

    /**
     * Generate a DROP TABLE query for the current table.
     *
     * @return string
     */
    public function drop()
    {
        return DB::query("DROP TABLE IF EXISTS `{$this->table}`;");
    }

    /**
     * Generate an ALTER TABLE query for the current table and fields.
     *
     * @return string
     */
    public function change()
    {
        $sql = "ALTER TABLE `{$this->table}` $this->change_action ";
        $sql .= implode(', ', $this->fields) . " $this->change_position";

        $this->reset();

        return DB::query($sql);
    }

    /**
     * Add a new column to the table.
     *
     * @param string $name
     * @param string $type
     * @param string|null $after
     * @return $this
     */
    public function addColumn()
    {
        $this->change_position = '';
        $this->change_action = 'ADD IF NOT EXISTS';
        return $this;
    }


    /**
     * Drop a column from the table.
     *
     * @param string $name
     * @return $this
     */
    public function dropColumn($name)
    {
        $this->change_position = '';
        $this->change_action = "DROP COLUMN IF EXISTS `$name`";
        return $this;
    }

    /**
     * Rename a column in the table.
     *
     * @param string $name
     * @param string $new_name
     * @param string $type
     * @return $this
     */
    public function renameColumn($current_name)
    {
        $this->change_position = '';
        $this->change_action = "CHANGE IF EXISTS `$current_name`";
        return $this;
    }


    public function after($column_name)
    {
        $this->change_position = "AFTER `$column_name`";
        return $this;
    }

    /**
     * Modify a column in the table.
     *
     * @param string $name
     * @param string $type
     * @return $this
     */
    public function modifyColumn()
    {
        $this->change_action = 'MODIFY COLUMN';
        return $this;
    }

    /**
     * Add an index to the table.
     *
     * @param string $name
     * @param array $columns
     * @param string $type
     * @return $this
     */
    public function addIndex($name, array $columns, $type = 'INDEX')
    {
        $this->fields[] = "ADD $type `$name` (" . implode(', ', $columns) . ")";
        return $this;
    }

    /**
     * Drop an index from the table.
     *
     * @param string $name
     * @return $this
     */
    public function dropIndex($name)
    {
        $this->fields[] = "DROP INDEX `$name`";
        return $this;
    }

    /**
     * Add a foreign key constraint to the table.
     *
     * @param string $name
     * @param string $column
     * @param string $table
     * @param string $references
     * @param string $onDelete
     * @param string $onUpdate
     * @return $this
     */
    public function addForeign($name, $column, $table, $references, $onDelete = 'CASCADE', $onUpdate = 'CASCADE')
    {
        $this->fields[] = "CONSTRAINT `$name` FOREIGN KEY (`$column`) REFERENCES `$table` (`$references`) ON DELETE $onDelete ON UPDATE $onUpdate";
        return $this;
    }

    /**
     * Drop a foreign key constraint from the table.
     *
     * @param string $name
     * @return $this
     */
    public function dropForeign($name)
    {
        $this->fields[] = "DROP FOREIGN KEY `$name`";
        return $this;
    }


    /**
     * Set the character set and collation of the last added column in the $fields array to utf8mb4.
     *
     * @param string $collation The collation to use. Default value is utf8mb4_unicode_ci.
     * @return Schema The current instance of the Schema class.
     */
    public function utf8mb4($collation = 'utf8mb4_unicode_ci')
    {
        $this->fields[count($this->fields) - 1] .= " CHARACTER SET utf8mb4 COLLATE $collation";
        return $this;
    }


    /**
     * Set the character set and collation of the last added column in the $fields array to utf8.
     *
     * @param string $collation The collation to use. Default value is utf8_unicode_ci.
     * @return Schema The current instance of the Schema class.
     */
    public function utf8($collation = 'utf8_unicode_ci')
    {
        $this->fields[count($this->fields) - 1] .= " CHARACTER SET utf8 COLLATE $collation";
        return $this;
    }
}