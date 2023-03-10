# CSV Utilities for Rose

```sh
composer require rsthn/rose-ext-csv
```

## Expression Functions

### `csv::load` filename:string tableName:string [extraFields:Map]
### `csv::loadTemp` filename:string tableName:string [extraFields:Map]
### `csv::read` filename:string

### `csv::clear`
### `csv::header` columNames:Arry
### `csv::row` values:Map|Arry
### `csv::rows` rows:Arry<Arry|Map>
### `csv::data` [clear:boolean]
### `csv::dump` filename:string [disposition:string]
