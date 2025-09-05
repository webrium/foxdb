<?php

namespace Foxdb;

use Foxdb\DB;

class Schema
{
    private string $table;
    private array $fields = [];
    private array $field_query = [
        'Action' => '',
        'Field' => '',
        'Type' => '',
        'Collation' => '',
        'Null' => false,
        'Default' => '',
        'Extra' => '',
        'Position' => ''
    ];

    private string $change_action = '';

    private bool $init_field_query = false;

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


    private function addExistsQueryToFieldsAndRest()
    {

        if ($this->init_field_query) {
            $this->fields[] = $this->field_query;

            $this->resetFieldQuery();
        }
    }

    private function resetFieldQuery(bool $force = false)
    {

        $this->field_query = [
            'Action' => '',
            'Field' => '',
            'Type' => '',
            'Collation' => '',
            'Null' => false,
            'Default' => '',
            'Extra' => '',
            'Position' => ''
        ];


        $this->init_field_query = false;
    }

    private function setFieldQuery(string $attribute, $value)
    {
        if (in_array(
            $attribute,
            [
                'Action',
                'Field',
                'Type',
                'Collation',
                'Null',
                'Default',
                'Extra',
                'Position'
            ]
        ) == false) {
            throw new \Exception("The field attribute name is not valid", 1);
        }

        $this->field_query[$attribute] = $value;
        $this->init_field_query = true;
    }


    private function reset()
    {
        $this->fields = [];
        $this->change_action = '';
        $this->resetFieldQuery();
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
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "$type UNSIGNED");
        $this->setFieldQuery('Extra', 'AUTO_INCREMENT PRIMARY KEY');

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
        $this->tinyInt($name, 1);
        $this->default(0);

        return $this;
    }


    /**
     * Adds a tiny int field to the table.
     *
     * @param  string  $name
     * @return $this
     */
    public function tinyInt($name, $length = 4)
    {
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "TINYINT($length)");

        return $this;
    }

    /**
     * Adds a medium int field to the table.
     *
     * @param  string  $name
     * @return $this
     */
    public function mediumInt($name, $length = 9)
    {
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "MEDIUMINT($length)");

        return $this;
    }


    /**
     * Adds a small int field to the table.
     *
     * @param  string  $name
     * @return $this
     */
    public function smallInt($name, $length = 6)
    {
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "SMALLINT($length)");

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
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "INT($length)");
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
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "BIGINT($length)");

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
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "VARCHAR($length)");

        return $this;
    }


    /**
     * Add a tiny text column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function tinyText($name)
    {

        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "TINYTEXT");

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

        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "TEXT");

        return $this;
    }


    /**
     * Add a medium text column to the table.
     *
     * @param string $name
     * @return $this
     */
    public function mediumText($name)
    {

        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "MEDIUMTEXT");

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
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "LONGTEXT");

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
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "JSON");

        return $this;
    }

    /**
     * Add created_at and updated_at timestamp columns to the table.
     *
     * @return $this
     */
    public function timestamps()
    {
        $this->dateTime('created_at')->default('CURRENT_TIMESTAMP', true);
        $this->timestamp('updated_at')->default('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', true);
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
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "DATETIME");

        return $this;
    }


    /**
     * Add a dateTime column to the table.
     *
     * @param string $name
     * @return $this
     */
    private function timestamp($name)
    {
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "TIMESTAMP");

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
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "TIME");

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
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "DATE");

        return $this;
    }

    /**
     * Add a float column to the table.
     *
     * @param string $name
     * @param string $length
     * @return $this
     */
    public function float($name)
    {
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "FLOAT");

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
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', 'YEAR(4)');

        return $this;
    }


    /**
     * Add a double column to the table.
     *
     * @param string $name
     * @param string $length
     * @return $this
     */
    public function double($name)
    {
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', 'DOUBLE');

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
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Type', "ENUM('" . implode("','", $values) . "')");

        return $this;
    }




    /**
     * Set a default value for the last column added to the table.
     *
     * @param string $value
     * @return $this
     */
    public function default($value, $directly = false)
    {
        if ($directly) {
            $this->setFieldQuery('Default', "DEFAULT $value");
        } else {
            $this->setFieldQuery('Default', "DEFAULT '$value'");
        }
        return $this;
    }

    /**
     * Set the last column added to the table as nullable.
     *
     * @return $this
     */
    public function nullable()
    {
        $this->setFieldQuery('Null', "NULL");
        return $this;
    }





    private function generateFieldQueryString(): string
    {
        if (empty($this->change_action) == false) {
            $this->setActionToAllFieldQuerys($this->change_action);
        }

        foreach ($this->fields as &$field) {
            if ($field['Null'] === false) {
                $field['Null'] = 'NOT NULL';
            }

            $field = implode(' ', $field);
        }

        return implode(',', $this->fields);
    }


    private function setActionToAllFieldQuerys(string $action): void
    {

        foreach ($this->fields as &$field) {
            $field['Action'] = $action;
        }
    }

    /**
     * Generate a DROP TABLE query for the current table.
     *
     * @return string
     */
    public function drop()
    {
        // Disable foreign key checks to allow dropping tables referenced by FKs
        DB::query("SET FOREIGN_KEY_CHECKS=0;");
        $result = DB::query("DROP TABLE IF EXISTS `{$this->table}`;");
        DB::query("SET FOREIGN_KEY_CHECKS=1;");
        return $result;
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
        $this->addExistsQueryToFieldsAndRest();
        $this->change_action = 'ADD COLUMN';
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
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Action', "DROP COLUMN `$name`");
        $this->setFieldQuery('Null', '');
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
        $this->addExistsQueryToFieldsAndRest();
        $this->change_action = "CHANGE COLUMN `$current_name`";
        return $this;
    }


    public function after($column_name)
    {
        $this->setFieldQuery('Position', "AFTER `$column_name`");
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
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Action', 'DROP INDEX');
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Null', '');

        return $this;
    }


    // for later
    // /**
    //  * Add a foreign key constraint to the table.
    //  *
    //  * @param string $name
    //  * @param string $column
    //  * @param string $table
    //  * @param string $references
    //  * @param string $onDelete
    //  * @param string $onUpdate
    //  * @return $this
    //  */
    // public function addForeign($name, $column, $table, $references, $onDelete = 'CASCADE', $onUpdate = 'CASCADE')
    // {
    //     $this->setFieldQuery('Action', "CONSTRAINT `$name` FOREIGN KEY (`$column`) REFERENCES `$table` (`$references`) ON DELETE $onDelete ON UPDATE $onUpdate");
    //     // $this->fields[] = ;
    //     return $this;
    // }

    // /**
    //  * Drop a foreign key constraint from the table.
    //  *
    //  * @param string $name
    //  * @return $this
    //  */
    // public function dropForeign($name)
    // {
    //     $this->fields[] = "DROP FOREIGN KEY `$name`";
    //     return $this;
    // }


    /**
     * Set the character set and collation of the last added column in the $fields array to utf8mb4.
     *
     * @param string $collation The collation to use. Default value is utf8mb4_unicode_ci.
     * @return Schema The current instance of the Schema class.
     */
    public function utf8mb4($collation = 'utf8mb4_unicode_ci')
    {
        $this->setFieldQuery('Collation', "CHARACTER SET utf8mb4 COLLATE $collation");
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
        $this->setFieldQuery('Collation', "CHARACTER SET utf8 COLLATE $collation");
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
        $this->addExistsQueryToFieldsAndRest();
        $this->change_action = 'MODIFY COLUMN';
        $this->setFieldQuery('Null', '');
        $this->init_field_query = false;
        return $this;
    }

    /**
     * Add an index to the table.
     *
     * @param string $name
     * @param array $columns
     * @param string $type @return $this
     */
    public function addIndex($name, array $columns, $type = 'INDEX')
    {
        $this->addExistsQueryToFieldsAndRest();
        $this->setFieldQuery('Action', "ADD $type");
        $this->setFieldQuery('Field', "`$name`");
        $this->setFieldQuery('Extra', "(" . implode(', ', $columns) . ")");
        $this->setFieldQuery('Null', '');

        return $this;
    }




    /**
     * Generate a CREATE TABLE query for the current table and fields.
     *
     * @return string
     */
    public function create($engine = 'InnoDB', $charset = 'utf8mb4', $collate = 'utf8mb4_unicode_ci')
    {
        $this->addExistsQueryToFieldsAndRest();

        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (";
        $sql .= $this->generateFieldQueryString();
        $sql .= ") ENGINE={$engine} DEFAULT CHARSET={$charset} COLLATE={$collate};";

        $this->reset();
        return DB::query($sql);
    }

    /**
     * Generate an ALTER TABLE query for the current table and fields.
     *
     * @return string
     */
    public function change()
    {
        $this->addExistsQueryToFieldsAndRest();
        $sql = "ALTER TABLE `{$this->table}` ";
        $sql .= $this->generateFieldQueryString();

        $this->reset();
        return DB::query($sql);
    }
}
