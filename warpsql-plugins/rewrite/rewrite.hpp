
#ifndef HAVE_REWRITE
#define HAVE_REWRITE 1

#include <algorithm> 
#include <functional> 
#include <cctype>
#include <locale>
#include <string>
#include <iostream>
#include <sstream>
#include <string.h>
#include <vector>
#include <assert.h>

#include <string>
#include <sstream>
#include <vector>

namespace REWRITER {

  namespace UTIL {
  
    inline std::vector<std::string> &split(const std::string &s, char delim, std::vector<std::string> &elems) {
        std::stringstream ss(s);
        std::string item;
        while (std::getline(ss, item, delim)) {
            elems.push_back(item);
        }
        return elems;
    }
    
    inline std::vector<std::string> split(const std::string &s, char delim) {
        std::vector<std::string> elems;
        split(s, delim, elems);
        return elems;
    }
    
    
    static inline std::string &ltrim(std::string &s) {
      s.erase(s.begin(), std::find_if(s.begin(), s.end(), std::not1(std::ptr_fun<int, int>(std::isspace))));
      return s;
    }
    
    static inline std::string &rtrim(std::string &s) {
      s.erase(std::find_if(s.rbegin(), s.rend(), std::not1(std::ptr_fun<int, int>(std::isspace))).base(), s.end());
      return s;
    }
    
    // trim from both ends
    static inline std::string &trim(std::string &s) {
      return ltrim(rtrim(s));
    }

  };/* UTIL namespace*/

  
  class RESULT {
    public:
    std::string sql; /* SQL to run to return final resultset */
    std::vector<std::string> table_names;  /* Tables to DROP after running the final SQL statement */

    ~RESULT() {
      /* TODO: drop tables here */
      drop_mysql_tables();
    }

    private drop_mysql_tables() {


    }
  };
  
    /* This is the complex MPP module.  It requires the gearmand 
     * daemon plugin be setup appropriately.
     * The MPP back-end must be set up.  It is based on Shard-Query
     * and supports both parallel query on a single host, but also
     * transparent sharding
     *
     * ALTER SYSTEM CREATE MPP USER [USERNAME] IDENTIFIED BY [PASSWORD]
     * ALTER SYSTEM CREATE MPP REPOSITORY [DB];
     * ALTER REPOSITORY [DB] ADD HOST [NAME=string] [HOST=string] [USER=string]
     * 
     * It utilizes gearman to execute SQL statements in 
     * parallel.  
     *
     * It requires the included gm_daemon, which is a gearman daemon
     * plugin. gm_daemon will spawn workers to
     * run SQL statements through.  It requires that Shard-Query
     * be setup, that the following TroySQL variables are set:
     * rewrite_filters="olap"
     * filter_params="olap=/path/to/shard-query.ini"
    */
    class MPP_REWRITE {
    
      public: 
      std::string sql;    /* SQL string to execute */  
      std::string schema; /* Schema in which to execute the SQL */
      REWRITE_RESULT result; /* A class in which the results will be stored */
    
      MPP_REWRITE(std::string sql, std::string schema) {
        this->sql = sql;
        this->schema = schema;
      }
    
      /* This is the procedure to override to implement your plugin
       * logic.  the rest of the class can be left as-is unless you
       * plan to change how plugins work.
       */
      int execute() {
        /* call shard-query with gearman client, get response
         * as JSON object:
         * array [ sql="SQL to execute", tables="CSV list of tables to drop" ]
         *
         * This procedure returns:
         * -1 if processing failed
         * 0 on success;
         */
         
         /*TODO: phpembed code here */
    
         /* return final SQL here */
         result.sql = "a new query!";
    
         /* construct the table list */
    
         return 0;
      }
    
    };

  /* This is the main entry class for TroySQL/MariaDB/MySQL 
   * into the rewriter.  
   *
   * It takes three parameters:
   * std::string  - the SQL to rewrite
   * std::string  - schema to execute it in
   * std::string  - CSV list of filters
   *
   * */
  class ENGINE {
  
    bool drop_table(std::string schema, std::string table_name) {
    /* TODO: Use MariaDB server drop table code */
    }
  
    private:
    std::string filters;
    std::string sql;
    std::string schema;
  
    public:
    ENGINE(std::string sql, std::string schema, std::string filter_list) {
      filters = filter_list;
      this->sql = sql;
      this->schema = schema;
    }
 
    /* Execute the rewrite process.  The SQL goes in, is processed by the
     * filter, and a REWRITER::RESULT object is returned, which is a 
     * SQL to execute by the actual SQL engine, and a list of tables
     * to drop.
     */ 
    RESULT execute() {
      int filter_count = 0;
      REWRITE_RESULT out;
      std::vector<std::string> vec = UTIL::split(filters, ',');
  
      for(std::vector<std::string>::size_type i=0;i<vec.size();i++) {
        /* remove begin/end whitespace and lowercase the string*/
        std::string filter = REWRITER::trim(vec[i]);
        std::transform(filter.begin(), filter.end(), filter.begin(), ::tolower);
  
        /* Currently supported:
         * none - does nothing to the query, is used only for test
         * test - changes all queries to "select 1"
         * mpp - the MPP rewrite layer.  Requires setup.  See SETUP_MPP.txt
         * flexviews - the Flexviews rewrite layer for creating materialized views
         */
           
        if(filter == "none") {
  
            out.sql = sql;
  
        } else if(filter == "mpp") {
  
            rewriter = new MPP_REWRITE(sql, schema);
            int res = rewriter->execute();
  
            /* only utilize the filter if it executed properly */
            if(res != -1) {
              /* the next filter gets the rewritten SQL, and this SQL 
               * is set to the output SQL (thus it will be the final
               * rewritten SQL if this is the last filter.
               * */
              sql = out.sql = rewriter->result.sql();  
  
              /* Add the list of tables to drop to the REWRITE_RESULT */
              for(std::vector<std::string>::size_type i=0;i<tables.size();++i) {
                out.table_names.push_back(tables[i]);
              }
  
            } else {
              /* When -1 then the OLAP filter failed, run the query as-is 
               * TODO: make this a configurable error.
               */
              out.sql = sql;
            }
  
        } else {
          /* TODO:This should do something better probably, but for now
           * where this code is called from must handle this and return a meaningful
           * error message.
           */
          throw std::string("unknown filter specified: ") + filter;
        }
        ++filter_count;
      }
      /* if a rewrite at any step fails for some reason, the original 
       * SQL will be executed or sent on to next filter. 
       *
       */
      out.sql = sql;
  
      return out;
    }
  
  }; /* end class engine */

}; /* end namespace */

int main(int *argc, char** argv) {
//  *enter_rewrite_engine(char *sql, char *schema, char *filter_list)
    RW = new REWRITER::ENGINE("select * from some_table where a>1", "test", "olap=/tmp/sq.ini");
    REWRITER::RESULT res = RW->execute();
    std::cout << res.sql << "\n" << res.table_names.size() << "\n";
    return 0;
  }
}
#endif
