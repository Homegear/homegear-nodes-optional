# Squeezebox

|p0|p1|p2|p3|p4|Description|
|---|---|---|---|---|---|
|play|||||Start the player playing|
|pause|(0,1,)||||Pause/unpause player. p1=1 to pause, p1=0 to unpause, omit p1 to toggle|
|stop|||||Stop the player|
|sleep|(0..n)||||(Play for p1 seconds and then sleep|
|playlist|play|'<item>'|||Replace the current playlist with the song, playlist or directory specified by p2|
|playlist|add|<item>|||Append the song, playlist or directory specified by p2 to the end of the current playlist|
|playlist|insert|'<item>'|||Insert the song, playlist or directory specified by p2 to be played immediately after the current song.|
|playlist|resume|'<playlist>'|||Replace the current playlist with the playlist specified by p2, starting at the song that was playing when the file was saved. (Resuming works only with M3U files saved with the playlist save command below.) Shortcut: use a bare playlist name (without leading directories or trailing .m3u suffix to load a playlist in the saved playlists folder.|
|playlist|loadalbum|'<genre>'|'<artist>'|'<album>'|Replace the current playlist with all songs for a given genre, artist, and album. Use a value of "*" for p2, p3, or p4 as a wildcard. Note: the server's cache must contain the items for this to work.|
|playlist|save|'<playlist>'|||Save a playlist file in the saved playlists directory. Accepts a playlist filename (without .m3u suffix) and saves in the top level of the playlists directory.|
|playlist|addalbum|<genre>|<artist>|<album>|Add all songs for a given genre, artist, and album. Use a value of "*" for p2, p3, or p4 as a wildcard. Note: the server's cache must contain the items for this to work.|
|playlist|clear||||Clear the current playlist|
|playlist|repeat|(0,1,2,)|||Change the repeat mode. p1=0 no repeat, stop at the end of the playlist, p1=1 repeat the current song, p1=2 repeat the entire playlist. Omit p1 to cycle through values.|
|playlist|shuffle|(0,1,2,)|||Shuffle the playlist. p1=0 no shuffle, p1=1 shuffle songs in the playlist, p1=2 shuffle albums in the playlist. Omit p1 to toggle shuffle mode.|
|playlist|move|<fromoffset>|<tooffset>||Move the song in the offset specified by p2 to the offset specified by p3 in the playlist.|
|playlist|delete|<songoffset>|||Delete the song in the playlist at the offset specified by p2|
|playlist|jump|<index>|||Make the song at the offset specified by p2 in the playlist the currently playing song|
|mixer|volume|(0 .. 100)|(-100 .. +100)|||Adjust the volume as specified by p2 within the range 0 to 100. Prefix the number with a + or - to make the change relative.|
|mixer|balance|(0 .. 100)|(-100 .. +100)|||(not implemented) Adjust the volume as specified by p2 within the range 0 (most left) to 100 (most right). Prefix the number with a + or - to make the change relative. A value 50 means no balance adjustment.|
|mixer|base|(0 .. 100)|(-100 .. +100)|||(not implemented) Adjust the base boost/cut as specified by p2 within the range 0 to 100. Prefix the number with a + or - to make the change relative. A value of 50 means no boost or cut.|
|mixer|treble|(0 .. 100)|(-100 .. +100)|||(not implemented) Adjust the treble boost/cut as specified by p2 within the range 0 to 100. Prefix the number with a + or - to make the change relative. A value of 50 means no boost or cut.|
|status|||||Return the current status of the player without any change.|
|display|<line1>|<line2>|<duration>||Put text on the player's display. line 1 is specified by p1, line 2 is specified by p2. The text is displayed for a number of seconds as specified by p3. If p3 is omitted, then a default time is used of approximately 1.5 seconds.|
|button|<code>||||Simulate a button press from the infrared remote, where <code> is a function name per the Default.map file.|
|rescan|||||Start a rescan of the music library|
|pref|<name>|<value>|||Set the value of a preference. Use a value of "?" to get the value in the returned p2 header|
|pref|<name>|?|||Get the value of a preference. The value is returned in the returned p2 header|
|playerpref|<name>|<value>|||Set the value of a player specific preference. Use a value of "?" to get the value in the returned p2 header|
|playerpref|<name>|?|||Get the value of a player-specific preference. The value is returned in the returned p2 header|
|debug|<flag>|<value>|||Set the value of a debugging variable, for example, set "d_ir" to "1" to have infrared debugging output.|
