import urllib, math, os, bz2, time, haversine

#Source: https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames
def deg2num(lat_deg, lon_deg, zoom):
	lat_rad = math.radians(lat_deg)
	n = 2.0 ** zoom
	xtile = int((lon_deg + 180.0) / 360.0 * n)
	ytile = int((1.0 - math.log(math.tan(lat_rad) + (1 / math.cos(lat_rad))) / math.pi) / 2.0 * n)
	return (xtile, ytile)

#Source: https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames
def num2deg(xtile, ytile, zoom):
	n = 2.0 ** zoom
	lon_deg = xtile / n * 360.0 - 180.0
	lat_rad = math.atan(math.sinh(math.pi * (1 - 2 * ytile / n)))
	lat_deg = math.degrees(lat_rad)
	return (lat_deg, lon_deg)

def FetchTile(x,y,zoom):
	folderName = "tiles/"+str(zoom)+"/"+str(x)
	tileFileName = folderName+"/"+str(y)+".osm.bz2"
	if os.path.exists(tileFileName):
		return

	nlat, wlon = num2deg(x,y,zoom)
	slat, elon = num2deg(x+1,y+1,zoom)
	
	url = "http://api.fosm.org/api/0.6/map?bbox="+str(wlon)+","+str(slat)+","+str(elon)+","+str(nlat)
	print url
	fi = urllib.urlopen(url)
	
	if not os.path.exists(folderName):
		os.makedirs(folderName)

	data = fi.read()

	out = bz2.BZ2File(tileFileName,"w")
	out.write(data)
	out.close()

if 0:
	x1,y1 = deg2num(51.0,-0.4,12)
	x2,y2 = deg2num(52.0,0.,12)
	zoom = 12
	jobList = []

	for x in range(x1,x2):
		print x
		for y in range(y2,y1):
			print x,y
			tlat, tlon = num2deg(x, y, zoom)
			dist = haversine.points2distance(((51.0,0.,0.),(-.4,0.,0.)),((tlat,0.,0.),(tlon,0.,0.)))
			#print dist
			jobList.append((dist,x,y,zoom))

	jobList.sort()

	print jobList[:10]
	print jobList[-10:]

	for dist,x,y,zoom in jobList:
		print dist,x,y,zoom
		FetchTile(x,y,zoom)
		time.sleep(1.)

for x in range(2040,2050):
	for y in range(1360,1390):
		FetchTile(x,y,12)

