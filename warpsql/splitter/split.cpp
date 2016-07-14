#include <iostream>
#include <ostream>
#include <string>
#include <stdlib.h>
#include <stdio.h>
#include <unordered_map>
#include <time.h>
#include "md5.h"
#include <string.h>
#include <chrono>
#include <my_global.h>
#include <mysql.h>
#include <sys/types.h>
#include <unistd.h>

#define BLOCK_SIZE (1024 * 1024)

typedef struct shard {
  long long id;
  long long schema_id;
  std::string shard_name;
  std::string extra_info;
  std::string shard_rdbms;
  bool coord_shard;
  bool accepts_new_rows;
  std::string user;
  std::string password;
  std::string host;
  int port;
  std::string db;
  bool enabled;
  std::string last_updated;
  shard() {};
    
} shard;

shard map(MYSQL* conn, long long schema_id, long long column_id, long long key_value);
shard map(MYSQL* conn, long long schema_id, long long column_id, std::string key_value);

int depth = 0;

shard populate_shard(MYSQL_ROW row) {
    shard s;
    s.id = atoll(row[0]);
    s.schema_id = atoll(row[1]);
    s.shard_name = std::string(row[2]);
    s.extra_info = std::string(row[3]);
    s.shard_rdbms = std::string(row[4]);
    s.coord_shard = atoi(row[5]);
    s.accepts_new_rows = atoi(row[6]);
    s.user = std::string(row[7]);
    s.password = std::string(row[8]);
    s.host = std::string(row[9]);
    s.port = atoi(row[10]);
    s.db = std::string(row[11]);
    s.enabled = atoi(row[12]);
    return s;
}


shard get_random_shard(MYSQL* conn,long long schema_id) {
  std::string sql = "select * from shards where schema_id=" + std::to_string(schema_id) + " order by rand() limit 1";
  if(int err = mysql_query(conn, sql.c_str())) {
    std::cerr << "ERROR: " << err << ", " << mysql_error(conn) << "\n";
    exit(-1);
  }
  MYSQL_RES *result = mysql_store_result(conn);
  MYSQL_ROW row = mysql_fetch_row(result);
  if(!row) {
    std::cerr << "ERROR: could not get random shard\n";
    exit(-1);
  }
  shard s;
  s = populate_shard(row);
  mysql_free_result(result);
  return s;
}

int retry_query(MYSQL* conn, std::string sql) {
  retry:
  if(!mysql_query(conn, sql.c_str())) return 0;
  int err = mysql_errno(conn);
  if(err == 1205 || err == 1213) goto retry; /* retry on deadlock or lock wait timeout */
  return err;
}

shard new_key(MYSQL* conn, long long schema_id, long long column_id, std::string key_value)  {
  std::string sql;
  shard s = get_random_shard(conn, schema_id);
  sql = "INSERT INTO shard_map_string (column_id, shard_id, key_value) VALUES (" + std::to_string(column_id) + "," + std::to_string(s.id) + "," + key_value + ")";
  int err = retry_query(conn, sql);
  if(err == 1062) {
    ++depth;
    s = map(conn, schema_id, column_id, key_value);  /* dupe key means someone else mapped first, get the mapping with map() */
    --depth;
  } else {
    /* any other error exits program */
    std::cerr << "ERROR: " << err << ", " << mysql_error(conn) << "\n";
    exit(-1);
  } 
  return s;
}

shard new_key(MYSQL* conn, long long schema_id, long long column_id, long long key_value) {
  std::string sql;
  shard s = get_random_shard(conn, schema_id);
  sql = "INSERT INTO shard_map (column_id, shard_id, key_value) VALUES (" + std::to_string(column_id) + "," + std::to_string(s.id) + "," + std::to_string(key_value) + ")";
  int err = retry_query(conn, sql);
  if(err == 1062) {
    ++depth;
    s = map(conn, schema_id, column_id, key_value);  /* dupe key means someone else mapped first, get the mapping with map() */
    --depth;
  } else if(err) {
    /* any other error exits program */
    std::cerr << "ERROR: " << err << ", " << mysql_error(conn) << "\n";
    exit(-1);
  } 
  return s;
}

shard map(MYSQL* conn, long long schema_id, long long column_id, long long key_value) {
  std::string sql;
  sql = "select shards.* from shard_map join shards on shard_id = shards.id where column_id =" + std::to_string(column_id) + " and key_value = " + std::to_string(key_value);
  if(int err = mysql_query(conn, sql.c_str())) {
    std::cerr << "ERROR: " << err << ", " << mysql_error(conn) << "\n";
    exit(-1);
  }
  MYSQL_RES *result = mysql_store_result(conn);
  MYSQL_ROW row = mysql_fetch_row(result);
  if(!row) {
    if(!depth) return(new_key(conn, schema_id, column_id, key_value));
    std::cerr << "ERROR: recursive mapping failed after new key creation failed.  Invalid state.\n";
    exit(-1);
  }
  shard s = populate_shard(row);
  mysql_free_result(result);
  return s;
}


shard map(MYSQL* conn, long long schema_id, long long column_id, std::string key_value) {
  std::string sql;
  sql = "select shard_name from shard_map_string join shards on shard_id = shards.id where column_id =" + std::to_string(column_id) + " and key_value = '" + key_value + "'";
  if(int err = mysql_query(conn, sql.c_str())) {
    std::cerr << "ERROR: " << err << ", " << mysql_error(conn) << "\n";
    exit(-1);
  }
  MYSQL_RES *result = mysql_store_result(conn);
  MYSQL_ROW row = mysql_fetch_row(result);
  if(!row) {
    if(!depth) return(new_key(conn, schema_id, column_id, key_value));
    std::cerr << "ERROR: recusive mapping failed after new key creation failed.  Invalid state.\n";
    exit(-1);
  }
  shard s = populate_shard(row);
  mysql_free_result(result);
  return s;
}

long long filesize(std::string filename) {
    FILE *p_file = fopen(filename.c_str(),"rb");
    if(!p_file) return(-1);
    long long z = -1;
    fseek(p_file,0,SEEK_END);
    z = ftell(p_file);
    fclose(p_file);
    return z;
}

int main(int argc, char**argv) {
  std::chrono::steady_clock::time_point begin = std::chrono::steady_clock::now();
  int i; /* for for loops */
  /* commandline args */
  std::string filename;
  long long start=-1;
  long long end=-1;
  std::string delimiter;
  int shard_pos=-1;
  std::string host;
  std::string user;
  int port=3306;
  std::string password;
  std::string mapper_db;
  std::string schema_name;
  std::string table;
  long long chunk_size = 256 * 1024 * 1024;

  /* fetched from db */
  std::string column_name;
  long long column_id = -1;
  long long schema_id = -1;
  std::string mapper_type;
  std::string shared_path="";
  std::string lookup_db="";


  std::unordered_map<std::string,FILE*>::const_iterator find_file; 
  std::unordered_map<std::string,shard>::const_iterator find_shard;
 

  /* Process commandline............................... */
  std::string new_cmdline = "";

  if(argc == 1) {
    std::cerr << "usage: split -t table -f filename -j start -e end -y delimiter -z shard_pos -h mapper_host -u mapper_user -p mapper_password -d mapper_db -s schema_name -q chunk_size [default 256MB] -P port\n";
    exit(-1);
  }
  for(i=1;i<argc;++i) {
    if(i+1>argc) {
      std::cerr << "ERROR: Argument processing error at: " << argv[i] << " missing argument!\n";
      exit(-1);
    }
    std::string k(argv[i]);
    if(k == "-t") {
        table.assign(argv[++i]);
        new_cmdline += " -t " + table;
        continue;
    }
    if(k == "-f") {
        filename.assign(argv[++i]);
        new_cmdline += " -f " + filename;
        continue;
    }
    if(k == "-y") {
        delimiter.assign(argv[++i]);
        new_cmdline += " -y \"" + delimiter + "\"";
        continue;
    }
    if(k == "-h") {
        host.assign(argv[++i]);
        new_cmdline += " -h " + host;
        continue;
    }
    if(k == "-u") {
        user.assign(argv[++i]);
        new_cmdline += " -u " + user;
        continue;
    }
    if(k == "-p") {
        password.assign(argv[++i]);
        new_cmdline += " -p " + password;
        continue;
    }
    if(k == "-d") {
        mapper_db.assign(argv[++i]);
        new_cmdline += " -d " + mapper_db;
        continue;
    }
    if(k == "-s") {
        schema_name.assign(argv[++i]);
        new_cmdline += " -s " + schema_name;
        continue;
    }
    if(k == "-j") {
        start = atoll(argv[++i]);
        continue;
    }
    if(k == "-e") {
        end = atoll(argv[++i]);
        continue;
    }
    if(k == "-z") {
        shard_pos = atoll(argv[++i]);
        continue;
    }
    if(k == "-q") {
        chunk_size = atoll(argv[++i]);
        continue;
    }
    if(k == "-P") {
        port = atoi(argv[++i]);
        continue;
    }
  
    std::cerr << "ERROR: Argument processing error.  Unexpected token: " << k << "\n";
    exit(-1);

  }

  if(schema_name.length() > 1024) {
    std::cerr << "ERROR: Invalid schema name!\n";
    exit(-1);
  }

  if(table.length() > 1024) {
    std::cerr << "ERROR: Invalid table name!\n";
    exit(-1);
  }

  if(table == "" || filename == "" || delimiter == "" || host == "" || user == "" || password == "" || mapper_db == "" || schema_name == "" || start == -1 || end == -1) {
    std::cerr << "ERROR: all parameters are required\n";
    exit(-1);
  }

  long long fsize = filesize(filename);

  if(start == 0 && end == 0) {
    std::cerr << "Filesize: " << fsize << "\n";
    for(long long i=0;i<fsize;i+=chunk_size) {
      std::cout<< new_cmdline << " -j " << i << " -e " << (i + chunk_size) << "\n";
    }
    exit(0);
  }

  /* last chunk is probably past eof, so fix it*/
  if(end > fsize) end = fsize;

  MYSQL *conn = mysql_init(NULL);
  
  if (conn == NULL) {
      fprintf(stderr, "ERROR: mysql_init() failed\n");
      exit(-1);
  }  

  /* establish a connection to the meta-server*/ 
  if (mysql_real_connect(conn, host.c_str(), user.c_str(), password.c_str(), mapper_db.c_str(), port, NULL, 0) == NULL) {
      std::cerr << "ERROR: " << std::string(mysql_error(conn)) << "\n";
      exit(-1);
  }    

  /* max strings allowed in input validation = 1024, so this holds 4x the amount of data needed for safety*/
  char buffer[4097];

  /* Get the column_name from the database */
  mysql_real_escape_string(conn, buffer,schema_name.c_str(),schema_name.length());
  std::string sql = "select sequence_name,column_sequences.id cs_id, schemata.id s_id,datatype from column_sequences join schemata on schema_id = schemata.id where schema_name='" + std::string(buffer) + "' and sequence_type='shard_column';";
  
  int err=0;

  if(err = mysql_query(conn, sql.c_str())) {
    std::cerr << "ERROR: " << err << ", " << mysql_error(conn) << "\n";
    return 0;
  }
  MYSQL_RES *result = mysql_store_result(conn);
  MYSQL_ROW row = mysql_fetch_row(result);
  if(!row) {
    std::cerr << "ERROR: schema '" << schema_name << "' does not exist\n";
    exit(-1);
  } 
 
  column_name.assign(row[0]);
  column_id = atoll(row[1]);
  schema_id = atoll(row[2]);
  std::string datatype(row[3]); 
  mysql_free_result(result);

  sql = "select var_value from schemata_config where schema_id = " + std::to_string(schema_id) + " and var_name = 'mapper';";
  if(err = mysql_query(conn, sql.c_str())) {
    std::cerr << "ERROR: " << err << ", " << mysql_error(conn) << "\n";
    return 0;
  }
  result = mysql_store_result(conn);
  row = mysql_fetch_row(result);
  mapper_type.assign(row[0]);
  mysql_free_result(result);

  sql = "select var_value from schemata_config where schema_id = " + std::to_string(schema_id) + " and var_name = 'shared_path';";
  if(err = mysql_query(conn, sql.c_str())) {
    std::cerr << "ERROR: " << err << ", " << mysql_error(conn) << "\n";
    return 0;
  }
  result = mysql_store_result(conn);
  row = mysql_fetch_row(result);
  shared_path.assign(row[0]);
  mysql_free_result(result);

  sql = "select var_value from schemata_config where schema_id = " + std::to_string(schema_id) + " and var_name = 'lookup_db';";
  if(err = mysql_query(conn, sql.c_str())) {
    std::cerr << "ERROR: " << err << ", " << mysql_error(conn) << "\n";
    return 0;
  }
  result = mysql_store_result(conn);
  row = mysql_fetch_row(result);
  lookup_db.assign(row[0]);
  mysql_free_result(result);

  shard s = get_random_shard(conn,schema_id);

  MYSQL *conn2 = mysql_init(NULL);
  
  if (conn2 == NULL) {
      fprintf(stderr, "ERROR: mysql_init() failed\n");
      exit(-1);
  }  

  if (mysql_real_connect(conn2, s.host.c_str(), s.user.c_str(), s.password.c_str(), s.db.c_str(), 0, NULL, 0) == NULL) {
      std::cerr << "ERROR: " << std::string(mysql_error(conn)) << "\n";
      exit(-1);
  }    

  mysql_real_escape_string(conn2, buffer,column_name.c_str(),column_name.length());
  char buffer2[4097];
  mysql_real_escape_string(conn2, buffer2,table.c_str(),table.length());

  sql = "select ifnull(max(ordinal_position),-1) from information_schema.columns where table_schema='" + s.db + "' and table_name='" + std::string(buffer2) +"' and column_name = '" + std::string(buffer) + "'";
  if(err = mysql_query(conn, sql.c_str())) {
    std::cerr << "ERROR: " << err << ", " << mysql_error(conn) << "\n";
    return 0;
  }

  result = mysql_store_result(conn);
  row = mysql_fetch_row(result);
  int column_pos = atoi(row[0]);
  mysql_free_result(result);

  /* done with shard MySQL*/
  mysql_close(conn2);

  std::unordered_map<std::string, char> files_to_load;
  bool find_first_terminator=(start != 0);
  int done = 0;
  char line[BLOCK_SIZE];
  int rb;
  int c;
  int wb;

  if(column_pos == -1) {
    /* if not using lookup_db, get each host and db, otherwise there is one lookup_db per host */
    if(lookup_db == "") {
      sql = "select shard_name,db from shards where schema_id = " + std::to_string(schema_id);
    } else {
      sql = "select min(shard_name) from shards where schema_id = " + std::to_string(schema_id) + " GROUP BY host";
    }

    if(err = mysql_query(conn, sql.c_str())) {
      std::cerr << "ERROR: " << err << ", " << mysql_error(conn) << "\n";
      return 0;
    }
    /* buffers result in ram, can close db connection subsequently*/
    result = mysql_store_result(conn); 
    mysql_close(conn);

    std::string path;
    int linecount=0;
    while((row = mysql_fetch_row(result))){
      std::string db;
      std::string host(row[0]);
      if(lookup_db != "") {
        path = shared_path + "/loader/split/" + std::string(row[0]) + "/" + lookup_db + "/" + table + "/"; 
        db = lookup_db;
      } else {
        path = shared_path + "/loader/split/" + std::string(row[0]) + "/" + std::string(row[1]) + "/" + table + "/";
        db = std::string(row[1]);
      }
      system(("mkdir -p " + std::string(path)).c_str());
      path += std::string(md5(std::to_string(getpid()))) + ".txt";
      files_to_load[host + "::" + db + "::" + table + "::" + path] = 1;
      FILE *fh = fopen(filename.c_str(), "rb");
      if(!fh) {
        std::cerr << "ERROR: could not open file for reading:" << filename << "\n";
        exit(-1);
      }
      FILE *fo = fopen(path.c_str(), "wb");
      if(!fo) {
        std::cerr << "ERROR: could not open file for writing:" << path << "\n";
        exit(-1);
      }

      /* simply copy input to output very fast */
      linecount = 0;
      while(!feof(fh)) {

        if(find_first_terminator) {
          fseek(fh,start,SEEK_SET);
          while((c = fgetc(fh))!='\n');
          find_first_terminator = 0;
        }

        if(fgets(line, BLOCK_SIZE,fh) == NULL) break;
        wb = fwrite(line, 1, rb = strlen(line), fo);
        ++linecount;
        if( wb != rb ) {
          std::cerr << "ERROR: io error\n";
          exit(-1);
        }

        if(ftell(fh) >= end) break;
      }      
      fclose(fh);
      fclose(fo);
    }
    std::chrono::steady_clock::time_point end= std::chrono::steady_clock::now();
    std::cerr << "LINES: " << linecount << " MAPS: 0 TIME(ms): " << std::chrono::duration_cast<std::chrono::milliseconds>(end - begin).count() ;
    mysql_free_result(result);
  } else {
    /* this table is sharded */

    sql = "select host,db,shard_name from shards where schema_id = " + std::to_string(schema_id);
    if(err = mysql_query(conn, sql.c_str())) {
      std::cerr << "ERROR: " << err << ", " << mysql_error(conn) << "\n";
      return 0;
    }
    /* buffers result in ram, can close db connection subsequently*/
    result = mysql_store_result(conn); 

    std::string path;
    int linecount=0;

    std::unordered_map<std::string, shard> shards;
    while((row = mysql_fetch_row(result))){
      shard s;
      s.host = std::string(row[0]);
      s.db = std::string(row[1]);
      s.shard_name = std::string(row[2]);
      shards[std::string(row[2])] = s;
    }

    mysql_free_result(result);
    // mysql_close(conn);
    FILE *fh = fopen(filename.c_str(), "rb");
    if(!fh) {
      std::cerr << "could not open file for reading:" << filename << "\n";
      exit(-1);
    }

    if(start != 0) {
      fseek(fh,start,SEEK_SET);
      while((c = fgetc(fh))!='\n');
    }

    std::unordered_map<std::string, FILE*> files;
    char delim = delimiter.c_str()[0];

    long long fpos = ftell(fh);
    char line[BLOCK_SIZE];
    int rb;
    int pos, s_pos,e_pos;
    FILE *fo;

    std::unordered_map<long long, shard> int_shard_cache; 
    std::unordered_map<std::string, shard> string_shard_cache; 
    std::unordered_map<long long,shard>::const_iterator find_int_shard_cache; 
    std::unordered_map<std::string,shard>::const_iterator find_string_shard_cache; 

    long long mapcount = 0;

    while(!feof(fh)) {
      if(fgets(line, BLOCK_SIZE,fh) == NULL) break;
      rb=strlen(line);

      if(line[rb-2] == delim) {
        line[rb-2]='\n';
        line[rb-1]='\0';
        rb-=1;
      }
      pos=1;
      std::string v = "";
      s_pos,e_pos=0;
      for(i=0;i<rb;++i) {
        if(line[i] == delim) {
          pos++;
          continue;
        }
        if(pos == column_pos) v += line[i];
        if(pos > column_pos) {
           break;
        }
      }

      shard s;
      if(datatype == "integer") {
        long long l = atoll(v.c_str());
        find_int_shard_cache = int_shard_cache.find(l);
        if (find_int_shard_cache != int_shard_cache.end()) {
          s = find_int_shard_cache->second; 
        } else {
          ++mapcount;
          s = map(conn, schema_id, column_id, l);
          int_shard_cache[l] = s;
        }
      } else {
        find_string_shard_cache = string_shard_cache.find(v);
        if (find_string_shard_cache != string_shard_cache.end()) {
          s = find_string_shard_cache->second; 
        } else {
          ++mapcount;
          s = map(conn, schema_id, column_id, v);
          string_shard_cache[v] = s;
        }
      }

      find_file = files.find(s.shard_name);
      if( find_file != files.end()) {
        fo = find_file->second;
      } else {
        find_shard = shards.find(s.shard_name);
        std::string path;
        if(find_shard != shards.end()) {
          path = shared_path + "/loader/split/" + (find_shard->second).host+ "/" + (find_shard->second).db + "/" + table + "/";
        } else {
          std::cerr << "ERROR: shard not found in unordered_map!\n";
          exit(-1);
        }
        system(("mkdir -p " + path).c_str());
        path += std::string(md5(std::to_string(getpid()))) + ".txt";
        files_to_load[(find_shard->second).shard_name + "::" + (find_shard->second).db + "::" + table + "::" + path] = 1;
        if(!(fo = fopen(path.c_str(), "wb"))) {
          std::cerr << "Could not open: " << path << " for writing\n";
          exit(-1);
        }
        files[s.shard_name] = fo;
      }

      wb = fwrite(line, 1, rb, fo);
      if( wb != rb ) {
        std::cerr << "ERROR: io error\n";
        exit(-1);
      }

      ++linecount;

      if(ftell(fh) >= end) break;
    }
    std::chrono::steady_clock::time_point end= std::chrono::steady_clock::now();
    std::cerr << "LINES: " << linecount << " MAPS: " << mapcount << " TIME(ms): " << std::chrono::duration_cast<std::chrono::milliseconds>(end - begin).count(); 

  }

 for ( auto it = files_to_load.begin(); it != files_to_load.end(); ++it ) {
    std::cerr << "|" << it->first  ;
 }
 std::cerr << "\n";
  
}
/*
int main(int argc, char** argv) {

 std::cout << map("default", "LO_OrderDateKey", "5","127.0.0.1", 4730) << "\n";
 std::cout << map("default", "LO_OrderDateKey", "5","127.0.0.1", 4730) << "\n";

}*/

