cmake_minimum_required(VERSION 3.16)
project(crestron_serial)

set(CMAKE_CXX_STANDARD 14)

include_directories(.)
include_directories(crestron-serial-in)
include_directories(crestron-serial-out)
include_directories(crestron-serial-port)

add_executable(crestron_serial
        crestron-serial-in/Factory.cpp
        crestron-serial-in/Factory.h
        crestron-serial-in/CrestronSerialIn.cpp
        crestron-serial-in/CrestronSerialIn.h
        crestron-serial-out/Factory.cpp
        crestron-serial-out/Factory.h
        crestron-serial-out/CrestronSerialOut.cpp
        crestron-serial-out/CrestronSerialOut.h
        crestron-serial-port/Factory.cpp
        crestron-serial-port/Factory.h
        crestron-serial-port/CrestronSerialPort.cpp
        crestron-serial-port/CrestronSerialPort.h
        crestron-serial-port/SerialReaderWriter.cpp
        crestron-serial-port/SerialReaderWriter.h)
