<?php

namespace webrium\foxql;

trait Query 
{
 /**
   * Retrieve the max of the values of a given column.
   *
   * @param  string  $column
   * @return mixed
   */
  public function max($column = '*')
  {
    $this->select(function ($query) use ($column) {
      $query->max($column)->as('max');
    });

    return $this->get_value($this->first(), 'max');
  }

  /**
   * Retrieve the min of the values of a given column.
   *
   * @param  string  $column
   * @return mixed
   */
  public function min($column = '*')
  {
    $this->select(function ($query) use ($column) {
      $query->min($column)->as('min');
    });

    return $this->get_value($this->first(), 'min');
  }
}
