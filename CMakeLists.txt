cmake_minimum_required(VERSION 3.8)
project(homegear_nodes_optional)

set(CMAKE_CXX_STANDARD 11)

set(SOURCE_FILES
        python/Factory.cpp
        python/Factory.h
        python/Python.cpp
        python/Python.h)

add_library(homegear_nodes_optional ${SOURCE_FILES})
