<?php namespace King\Orm\Dev;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;


class SchemaTool
{
    public static function createSchemaFromDir( $dir )
    {
        $schema = new Schema();

        foreach ( glob( $dir . '/*.yml' ) as $file ) {
            SchemaTool::createTableFromFile( $file, $schema );
        }

        return $schema;
    }

    public static function createTableFromFile( $file, Schema $schema = null )
    {
        if ( !$schema ) {
            $schema = new Schema();
        }

        $yml = file_get_contents( $file );
        try {
            $r = Yaml::parse( $yml );
        } catch ( ParseException $e ) {
            throw new \Exception( sprintf( 'yaml parse error at "%s": %s', basename( $file ), $e->getMessage() ) );
        }


        //this is create table!

        if ( isset( $r[ 'schema_ext' ] ) && !empty( $r[ 'schema_ext' ][ 'idx' ] ) ) {
            $idxStr = $r[ 'schema_ext' ][ 'idx' ];
            $extLink = isset($r[ 'schema_ext' ]['link']) ? $r[ 'schema_ext' ]['link'] : '';
            list( $idxMin, $idxMax ) = explode( '-', $idxStr );
            $idxMin = trim($idxMin);
            $idxMax = trim($idxMax);
            for( $idx=$idxMin;$idx<= $idxMax;$idx++){
                $r[ 'nameExt' ] = sprintf( '%s_%s', $r[ 'name' ], $idx );
                self::_createTableFromFile( $r, $schema );
            }
        } else {
            return self::_createTableFromFile( $r, $schema );
        }


    }


    static function _createTableFromFile( $r, Schema $schema = null )
    {
        $table = (isset( $r[ 'nameExt' ]) && $r['nameExt'] ) ? $schema->createTable( $r[ 'nameExt' ] ) :  $schema->createTable( $r[ 'name' ] );
        foreach ( $r[ 'columns' ] as $col ) {
            $option = isset( $col[ 'option' ] ) ? $col[ 'option' ] : [ ];

            if ( isset( $col[ 'comment' ] ) ) {
                $option[ 'comment' ] = $col[ 'comment' ];
            }

            $table->addColumn( $col[ 'name' ], $col[ 'type' ], $option );
        }

        if ( isset( $r[ 'indexes' ] ) ) {
            foreach ( $r[ 'indexes' ] as $idx ) {
                $name = isset( $idx[ 'name' ] ) ?
                    $idx[ 'name' ] :
                    self::getIndexName( $r[ 'name' ], $idx[ 'columns' ] );
                $flags = isset( $idx[ 'flags' ] ) ? $idx[ 'flags' ] : [ ];
                $option = isset( $idx[ 'option' ] ) ? $idx[ 'option' ] : [ ];

                if ( isset( $idx[ 'comment' ] ) ) {
                    $option[ 'comment' ] = $idx[ 'comment' ];
                }

                if ( isset( $idx[ 'unique' ] ) && $idx[ 'unique' ] ) {
                    $table->addUniqueIndex( $idx[ 'columns' ], $name, $option );
                } elseif ( isset( $idx[ 'columns' ] ) && !is_null( $idx[ 'columns' ] ) && is_array( $idx[ 'columns' ] ) ) {
                    $table->addIndex( $idx[ 'columns' ], $name, $flags, $option );
                } else {
                    echo sprintf( "Notice: Table [ %s ]  Index set is [ %s ]\n", $r[ 'name' ], json_encode( $idx[ 'columns' ] ) );
                }
            }
        }

        if ( isset( $r[ 'pk' ] ) && !empty( $r[ 'pk' ] ) ) {
            $table->setPrimaryKey( $r[ 'pk' ] );
        } else {
            $pkv = json_encode( $r[ 'pk' ] );
            echo "Notice: Table [ {$r['name']} ]  pk set is  [ {$pkv} ]" . PHP_EOL;

        }

        if ( isset( $r[ 'charset' ] ) ) {
            $table->addOption( 'charset', $r[ 'charset' ] );
        }
        if ( isset( $r[ 'collate' ] ) ) {
            $table->addOption( 'collate', $r[ 'collate' ] );
        }

        return $schema;
    }

    public static function reverseYmlFromTable( Table $table )
    {
        $r = [ ];
        $r[ 'name' ] = $table->getName();
        $r[ 'columns' ] = [ ];
        foreach ( $table->getColumns() as $col ) {
            $arrColumn = [
                'name' => $col->getName(),
                'type' => $col->getType()->getName(),
            ];

            $colComment = $col->getComment();
            if ( !empty( $colComment ) ) {
                $arrColumn += [
                    'comment' => $colComment,
                ];
            }

            $option = [ ];

            $obj = new \ReflectionObject( $col );
            foreach ( $obj->getMethods() as $method ) {
                $name = $method->getName();
                if ( substr( $name, 0, 3 ) !== 'get' ) {
                    continue;
                }
                if ( $method->getNumberOfParameters() !== 0 ) {
                    continue;
                }

                $value = $method->invoke( $col );

                if ( empty( $value ) ) {
                    continue;
                }
                $optName = strtolower( substr( $name, 3 ) );
                if ( in_array( $optName, [
                    'type',
                    'name',
                    'comment',
                    'notnull',
                ] ) ) {
                    continue;
                }

                if ( $optName === 'precision' && $value === 10 ) {
                    continue;
                }

                $option[ $optName ] = $value;
            }

            if ( !$col->getNotnull() ) {
                $option[ 'notnull' ] = false;
            }

            if ( !empty( $option ) ) {
                $arrColumn += [
                    'option' => $option,
                ];
            }

            $r[ 'columns' ][] = $arrColumn;
        }

        $r[ 'indexes' ] = [ ];
        foreach ( $table->getIndexes() as $idx ) {
            if ( $idx->isPrimary() ) {
                continue;
            }
            $arrColumn = [
                'columns' => $idx->getColumns(),
            ];

            $name = $idx->getName();
            if ( $name !== self::getIndexName( $table->getName(), $idx->getColumns() ) ) {
                $arrColumn += [
                    'name' => $name
                ];
            }

            $options = $idx->getOptions();
            if ( isset( $options[ 'comment' ] ) ) {
                $comment = $options[ 'comment' ];
                unset( $options[ 'comment' ] );
            } else {
                $comment = '';
            }
            if ( !empty( $options ) ) {
                $arrColumn += [
                    'option' => $options,
                ];
            }
            if ( !empty( $comment ) ) {
                $arrColumn += [
                    'comment' => $comment,
                ];
            }

            $flags = $idx->getFlags();
            if ( !empty( $flags ) ) {
                $arrColumn += [
                    'flags' => $flags,
                ];
            }

            if ( $idx->isUnique() ) {
                $arrColumn[ 'unique' ] = true;
            }

            $r[ 'indexes' ][] = $arrColumn;
        }

        $r[ 'pk' ] = $table->getPrimaryKey()->getColumns();

        return Yaml::dump( $r, 10, 2 );
    }

    protected static function getIndexName( $tableName, $cols )
    {
        return 'idx_' . base_convert( crc32( json_encode( [ $tableName, $cols ] ) ), 10, 36 );
    }
}
