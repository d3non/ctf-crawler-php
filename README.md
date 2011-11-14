this needs the modules posix.so and sysvsem.so enabled in php.ini

it is planed that one central TargetStore is used, but because php only knows 
forking (new process with memcopy of old one; not a thread!) we would have to use 
the shm_* functions. this is planed for the future!
