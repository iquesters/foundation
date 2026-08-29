<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<SQL
DROP TRIGGER IF EXISTS navigation_metas_navigation_items_sort_order_bi;
SQL);

        DB::unprepared(<<<SQL
CREATE TRIGGER navigation_metas_navigation_items_sort_order_bi
BEFORE INSERT ON navigation_metas
FOR EACH ROW
BEGIN
    DECLARE total_items INT DEFAULT 0;
    DECLARE current_index INT DEFAULT 0;
    DECLARE seen_keys LONGTEXT DEFAULT '';
    DECLARE current_key VARCHAR(255);
    DECLARE current_sort_order VARCHAR(50);
    DECLARE current_placement VARCHAR(100);
    DECLARE current_parent_id VARCHAR(100);

    IF NEW.meta_key = 'navigation_items' AND NEW.meta_value IS NOT NULL THEN
        SET total_items = JSON_LENGTH(NEW.meta_value);

        WHILE current_index < total_items DO
            SET current_sort_order = JSON_UNQUOTE(JSON_EXTRACT(NEW.meta_value, CONCAT('$[', current_index, '].sort_order')));

            IF current_sort_order IS NOT NULL AND current_sort_order <> '' THEN
                SET current_placement = COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(NEW.meta_value, CONCAT('$[', current_index, '].placement'))),
                    JSON_UNQUOTE(JSON_EXTRACT(NEW.meta_value, CONCAT('$[', current_index, '].section'))),
                    'sidebar'
                );
                SET current_parent_id = COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(NEW.meta_value, CONCAT('$[', current_index, '].parent_id'))),
                    '__root__'
                );
                SET current_key = CONCAT(current_placement, '|', current_parent_id, '|', CAST(current_sort_order AS SIGNED));

                IF LOCATE(CONCAT('|', current_key, '|'), CONCAT('|', seen_keys, '|')) > 0 THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Duplicate sort_order found at the same level in navigation_items.';
                END IF;

                SET seen_keys = CONCAT(seen_keys, current_key, '|');
            END IF;

            SET current_index = current_index + 1;
        END WHILE;
    END IF;
END;
SQL);

        DB::unprepared(<<<SQL
DROP TRIGGER IF EXISTS navigation_metas_navigation_items_sort_order_bu;
SQL);

        DB::unprepared(<<<SQL
CREATE TRIGGER navigation_metas_navigation_items_sort_order_bu
BEFORE UPDATE ON navigation_metas
FOR EACH ROW
BEGIN
    DECLARE total_items INT DEFAULT 0;
    DECLARE current_index INT DEFAULT 0;
    DECLARE seen_keys LONGTEXT DEFAULT '';
    DECLARE current_key VARCHAR(255);
    DECLARE current_sort_order VARCHAR(50);
    DECLARE current_placement VARCHAR(100);
    DECLARE current_parent_id VARCHAR(100);

    IF NEW.meta_key = 'navigation_items' AND NEW.meta_value IS NOT NULL THEN
        SET total_items = JSON_LENGTH(NEW.meta_value);

        WHILE current_index < total_items DO
            SET current_sort_order = JSON_UNQUOTE(JSON_EXTRACT(NEW.meta_value, CONCAT('$[', current_index, '].sort_order')));

            IF current_sort_order IS NOT NULL AND current_sort_order <> '' THEN
                SET current_placement = COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(NEW.meta_value, CONCAT('$[', current_index, '].placement'))),
                    JSON_UNQUOTE(JSON_EXTRACT(NEW.meta_value, CONCAT('$[', current_index, '].section'))),
                    'sidebar'
                );
                SET current_parent_id = COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(NEW.meta_value, CONCAT('$[', current_index, '].parent_id'))),
                    '__root__'
                );
                SET current_key = CONCAT(current_placement, '|', current_parent_id, '|', CAST(current_sort_order AS SIGNED));

                IF LOCATE(CONCAT('|', current_key, '|'), CONCAT('|', seen_keys, '|')) > 0 THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Duplicate sort_order found at the same level in navigation_items.';
                END IF;

                SET seen_keys = CONCAT(seen_keys, current_key, '|');
            END IF;

            SET current_index = current_index + 1;
        END WHILE;
    END IF;
END;
SQL);

    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS navigation_metas_navigation_items_sort_order_bi;');
        DB::unprepared('DROP TRIGGER IF EXISTS navigation_metas_navigation_items_sort_order_bu;');
    }
};
