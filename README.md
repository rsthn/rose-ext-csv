# CSV Utilities for Rose

```sh
composer require rsthn/rose-ext-csv
```

# Functions

### (`csv:load` filename:str table_name:str extra_fields:dict?)
Loads a CSV file into a table. Each column can optionally have a type which will be mapped to a
SQL type. The suffix can be one of the following:
| Suffix | SQL Type |
| ------ | -------- |
| :date | DATE |
| :date:d/m/y | DATE |
| :int | INT(10) |
| :primary | INT(10) PRIMARY KEY AUTO_INCREMENT |
| :numeric | DECIMAL(12,2) |
| :text | VARCHAR(4096) |
| :clean | VARCHAR(4096) |
| \<default> | VARCHAR(256) |

### (`csv:load-temp` filename:str table_name:str extra_fields:dict?)
Loads a CSV file into a temporary table. Each column can optionally have a type which will be
mapped to a SQL type. See: `csv:load` for more information.

### (`csv:read` filename:str header:list?)
Reads a CSV file into memory.

### (`csv:clear` auto_header:bool=false)
Clears the output CSV buffer.

### (`csv:separator` separator:str)
Specifies the column separator character.

### (`csv:escape` escape:bool)
Indicates whether or not to escape the CSV values in the output.

### (`csv:row-count`)
Number of rows in the output CSV.

### (`csv:header` column_names:list\<str>)
Specifies the column headers for the output CSV.

### (`csv:row` values:oneOf\<dict, list>)
Adds a row of data to the output CSV.

### (`csv:rows` rows:list\<oneOf\<dict, list>>))
Adds multiple rows of data to the output CSV.

### (`csv:data` clear_after_read:bool=false)
Returns the output CSV buffer.

### (`csv:dump` filename:str disposition:str?)
Dumps the output CSV buffer to the browser.

### (`csv:write` filename:str BOM:bool=true)
Writes the output CSV buffer to a file.
