<?php

namespace Foxdb;

class Schema
{
    private $table;
    private $fields;

    /**
     * Constructor method to set the table name.
     *
     * @param string $table
     */
    public function __construct($table)
    {
        $this->table = $table;
    }

    /**
     * Add an auto-incrementing primary key column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function id($name = 'id')
    {
        $this->fields[] = "`$name` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }

    /**
     * Add an auto-incrementing primary key column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function increments($name = 'id')
    {
        $this->fields[] = "`$name` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }

    /**
     * Add an integer column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function integer($name)
    {
        $this->fields[] = "`$name` INT(11)";
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
    public function create()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (";
        $sql .= implode(', ', $this->fields);
        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        return $sql;
    }

    /**
     * Generate a DROP TABLE query for the current table.
     *
     * @return string
     */
    public function drop()
    {
        return "DROP TABLE IF EXISTS `{$this->table}`;";
    }

    /**
     * Generate an ALTER TABLE query for the current table and fields.
     *
     * @return string
     */
    public function change()
    {
        $sql = "ALTER TABLE `{$this->table}` ";
        $sql .= implode(', ', $this->fields);

        return $sql;
    }

    /**
     * Add a new column to the table.
     *
     * @param string $name
     * @param string $type
     * @param string|null $after
     * @return $this
     */
    public function addColumn($name, $type, $after = null)
    {
        $column = "`$name` $type";
        if ($after) {
            $column .= " AFTER `$after`";
        }
        $this->fields[] = $column;

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
        $this->fields[] = "DROP COLUMN `$name`";
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
    public function renameColumn($name, $new_name, $type)
    {
        $this->fields[] = "CHANGE `$name` `$new_name` $type";
        return $this;
    }

    /**
     * Modify a column in the table.
     *
     * @param string $name
     * @param string $type
     * @return $this
     */
    public function modifyColumn($name, $type)
    {
        $this->fields[] = "MODIFY COLUMN `$name` $type";
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
    public function addIndex($name, $columns, $type = 'INDEX')
    {
        $this->fields[] = "$type `$name` (" . implode(', ', $columns) . ")";
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
}