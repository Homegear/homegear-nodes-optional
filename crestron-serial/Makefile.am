AUTOMAKE_OPTIONS = subdir-objects

AM_CPPFLAGS = -Wall -std=c++11 -DFORTIFY_SOURCE=2 -DGCRYPT_NO_DEPRECATED
AM_LDFLAGS = -Wl,-rpath=/lib/homegear -Wl,-rpath=/usr/lib/homegear -Wl,-rpath=/usr/local/lib/homegear
LIBS += -Wl,-Bdynamic -lhomegear-node

libdir = $(localstatedir)/lib/homegear/node-blue/nodes/crestron-serial

lib_LTLIBRARIES = crestron-serial-in.la crestron-serial-out.la crestron-serial-port.la

crestron_serial_in_la_SOURCES = crestron-serial-in/Factory.cpp crestron-serial-in/CrestronSerialIn.cpp
crestron_serial_in_la_LDFLAGS =-module -avoid-version -shared

crestron_serial_out_la_SOURCES = crestron-serial-out/Factory.cpp crestron-serial-out/CrestronSerialOut.cpp
crestron_serial_out_la_LDFLAGS =-module -avoid-version -shared

crestron_serial_port_la_SOURCES = crestron-serial-port/Factory.cpp crestron-serial-port/CrestronSerialPort.cpp crestron-serial-port/SerialReaderWriter.cpp
crestron_serial_port_la_LDFLAGS =-module -avoid-version -shared

crestron_ladir = $(libdir)
crestron_la_DATA = crestron-serial-in/crestron-serial-in.hni crestron-serial-out/crestron-serial-out.hni crestron-serial-port/crestron-serial-port.hni crestron.png
locale_en_usdir = $(libdir)/locales/en-US
locale_en_us_DATA = crestron-serial-in/locales/en-US/crestron-serial-in crestron-serial-out/locales/en-US/crestron-serial-out crestron-serial-port/locales/en-US/crestron-serial-port
locale_de_dedir = $(libdir)/locales/de-DE
locale_de_de_DATA = crestron-serial-in/locales/de-DE/crestron-serial-in crestron-serial-out/locales/de-DE/crestron-serial-out crestron-serial-port/locales/de-DE/crestron-serial-port

install-exec-hook:
	rm -f $(DESTDIR)$(libdir)/crestron-serial-in.la
	rm -f $(DESTDIR)$(libdir)/crestron-serial-out.la
	rm -f $(DESTDIR)$(libdir)/crestron-serial-port.la
