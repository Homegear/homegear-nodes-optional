from homegear import Homegear
import threading
import sys
import asyncio

from pyatv import connect, const, scan
from pyatv.const import Protocol
from pyatv.interface import (
	App,
	Playing,
	RemoteControl,
	PushListener,
	PowerListener,
	DeviceListener,
	retrieve_commands,
)
from pyatv.scripts import TransformProtocol, VerifyScanHosts, TransformOutput

loop = asyncio.get_event_loop()
atv = None
nodeInfo = None
hg = None

startUpComplete = threading.Condition()

# This callback method is called on Homegear variable changes.
def eventHandler(eventSource, peerId, channel, variableName, value):
	# Note that the event handler is called by a different thread than the main thread. I. e. thread synchronization is
	# needed when you access non local variables.
	# See https://effbot.org/zone/thread-synchronization.htm
	
	# When the flows are fully started, "startUpComplete" is set to "true". Wait for this event.
	if eventSource == "nodeBlue" and peerId == 0 and variableName == "startUpComplete":
		startUpComplete.acquire()
		global nodeInfo
		nodeInfo = value
		startUpComplete.notify()
		startUpComplete.release()
	

# This callback method is called when a message arrives on one of the node's inputs.
def nodeInput(nodeInfo, inputIndex, message):
	# Note that the event handler is called by a different thread than the main thread. I. e. thread synchronization is
	# needed when you access non local variables.
	# See https://effbot.org/zone/thread-synchronization.htm
	asyncio.run_coroutine_threadsafe(nodeInputThreadSafe(nodeInfo, inputIndex, message), loop).result()

async def nodeInputThreadSafe(nodeInfo, inputIndex, message):
	if inputIndex == 0:
		if message["payload"]:
			await atv.power.turn_on()
		else:
			await atv.power.turn_off()


class PushNodeOutput(PushListener):
	def __init__(self, atv):
		self.atv = atv

	def playstatus_update(self, updater, playstatus: Playing) -> None:
		hg.nodeOutput(2, {"payload": playstatus.device_state.name})
		hg.nodeOutput(3, {"payload": {
			"state": playstatus.device_state.name,
			"album": playstatus.album,
			"artist": playstatus.artist,
			"genre": playstatus.genre,
			"hash": playstatus.hash,
			"media_type": playstatus.media_type.name,
			"position": playstatus.position,
			"repeat": playstatus.repeat.name,
			"shuffle": playstatus.shuffle.name,
			"title": playstatus.title,
			"total_time": playstatus.total_time
		}})

	def playstatus_error(self, updater, exception: Exception) -> None:
		pass

class PowerNodeOutput(PowerListener):
	def powerstate_update(self, old_state: const.PowerState, new_state: const.PowerState):
		hg.nodeOutput(1, {"payload": new_state == const.PowerState.On})

class DeviceNodeOutput(DeviceListener):
	def connection_lost(self, exception):
		hg.nodeOutput(0, {"payload": False})
		sys.stdout.flush()
		sys.exit(2)

	def connection_closed(self):
		hg.nodeOutput(0, {"payload": False})
		sys.stdout.flush()
		sys.exit(2)

async def autodiscoverDevice(loop):
	options = {}

	atvs = await scan(loop, **options)

	if not atvs:
		return None

	apple_tv = atvs[0]

	return apple_tv

async def appstart(loop, hg):
	config = await autodiscoverDevice(loop)
	if not config:
		return 1
	
	global atv
	atv = await connect(config, loop, protocol=Protocol.MRP)
	try:
		power_listener = PowerNodeOutput()
		device_listener = DeviceNodeOutput()
		push_listener = PushNodeOutput(atv)

		atv.power.listener = power_listener
		atv.listener = device_listener
		atv.push_updater.listener = push_listener
		atv.push_updater.start()
		await atv.power.turn_off() #Turn off Apple TV after reconnect
		hg.nodeOutput(0, {"payload": True})
		while hg.connected():
			await asyncio.sleep(1)
	finally:
		atv.close()

	return 0

# hg waits until the connection is established (but for a maximum of 2 seonds).
# The socket path is passed in sys.argv[1], the node's ID in sys.argv[2]
hg = Homegear(sys.argv[1], eventHandler, sys.argv[2], nodeInput);

# Wait for the flows to start.
startUpComplete.acquire()
while hg.connected():
	if startUpComplete.wait(1) == True:
		break
startUpComplete.release()

# The node is now fully started. Start event loop.

loop.run_until_complete(appstart(loop, hg))
