rets-laravel
===

Laravel obstruction for flexmls and other RETS compatible MLS systems


## Feature (aka The Plan)
* Cache API structure for easy re-use
* Generate schema based on API response
* Provide tool for initial download
* Provide tool for keeping data up to date
* Provide tool for easy purging
* Build basic model for searching and filtering
 
## Components
* Connection
* Repo/Interface
* Schema Generator
* Helper schema for API information
* Commands
** rets:install
** rets:setup
** rets:update
 
 
 
 php artisan config:publish package="mikhailkozlov/rets-laravel"