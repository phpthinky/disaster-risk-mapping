php migrate.php migrate             # run pending migrations
php migrate.php migrate:status      # check what ran / pending
php migrate.php migrate:rollback    # undo last batch
php migrate.php migrate:fresh       # drop all + restart (dev only!)
php migrate.php db:seed             # seed barangays, hazard types, users
php migrate.php make:migration add_something_table  # create new file