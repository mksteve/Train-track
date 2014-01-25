require(["dojo/ready",  
	"dojox/geo/openlayers/Map", 
	"dojox/geo/openlayers/GeometryFeature", 
	"dojox/geo/openlayers/Point", 
	"dojox/geo/openlayers/GfxLayer", 
	"dojox/geo/openlayers/LineString", 
	"dojox/geo/openlayers/Layer",
	 "dojo/dom-construct",
	 "dojo/store/JsonRest",
	 "dojox/timing",
	 "dojo/on",
	 "dojo/dom",
	 "dojo/date",
	 "dojo/domReady!" ], function(ready, Map,GeometryFeature, Point, GfxLayer, LineString, Layer, domConstruct, jrest, timething,on, dom, ddate ){

	ready(function(){
	    if (typeof Date.now == "undefined") {
		    Date.now = function(){return new Date().getTime()};
	    }
	    var delayDiv = 3000;
	    var startDiv = 0;
		function bound(x, fm ) {
		    var trainDetails = function(e){
			if( startDiv + delayDiv > Date.now() ){
			    return; // only delayDiv msecs since put up div.
			}
			startDiv = Date.now();
			domConstruct.destroy( "traintipdiv" );

			layer.removeFeature( wayPoints );
			wayPoints = new  Array();
			var p = new Point({x: Number( x.long_) , y:Number( x.Lat) });
			// create a GeometryFeature
			var f = new GeometryFeature(p);
			f.setFill([ 255,0,0 ]);
			f.setStroke([ 0, 0, 0 ]);
			f.setShapeProperties({
				r : 10
				    });
			wayPoints[ wayPoints.length] = f;
			layer.addFeature( f );
			layer.redraw();
			var bg = domConstruct.create( "div", {
				id : 'traintipdiv',
				className : 'dijitTooltip dijitTooltipBelow dijitTooltipABLeft',
				style : {
				    right: 'auto',
				    left : e.pageX + 'px',
				    width : 'auto',
				    top : e.pageY + 'px'
				}
			    }, dom.byId( "map") );
			var fg = domConstruct.create("div",{
				className : 'dijitTooltipContainer dijitTooltipContents',
				innerHTML : "train " + x.id,
				style : {
				    align:'left',
				    fontSize : '12px',
				    role:'alert'
				}
			    }, bg);   
			var train_times = new jrest({
				target: "./train_.php/vwTrainTimeTableAll/"
			    });
			train_times.get( x.id ).then( function(r){
				var table = dojo.create('table', {}, fg);
				var tbody = dojo.create('tbody', {}, table); // a version of IE needs this or it won't render the table
				var journey = new Array();
				for( i =0; i < r.length;i++ ){
				    var tr = dojo.create( 'tr', {} , tbody );
				    dojo.create( 'td', { innerHTML : r[i].Name} , tr );
				    dojo.create( 'td', { innerHTML : r[i].tme || "" } , tr );
				    journey[journey.length] = { name: r[i].Name, x: Number(r[i].long_), y: Number(r[i].lat) };
				}
				var lns = new LineString( journey );
				var f = new GeometryFeature( lns );
				f.setStroke([ 0, 0, 0 ]);
				
				layer.addFeature( f );
				wayPoints[wayPoints.length ] = f;
				p = new Point({x: Number(r[r.length-1].long_), y: Number(r[r.length-1].lat) });
				// create a GeometryFeature
				f = new GeometryFeature(p);
				f.setFill([ 0,255,0 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 10
				    });
				wayPoints[ wayPoints.length] = f;
			
				layer.addFeature( f );
				layer.redraw();
			    });
		    }
			    
		    fm.mouseover = on( fm.feature.getShape().getEventSource(),"mouseover", trainDetails );
		    var trainRemove = function(e){
			if( startDiv + delayDiv > Date.now() ){
			    return; // only 5 secs since put up div.
			}
			domConstruct.destroy( "traintipdiv" );
			layer.removeFeature( wayPoints );
			wayPoints = new Array();
		    }
		    fm.mouseout = on( fm.feature.getShape().getEventSource(),"mouseout", trainRemove );
		}



		function toMinutes( tme ){
		    return parseInt( tme.substring( 0,2 )) * 60 + parseInt(tme.substring(2,4) );
		}
		map = new Map("map", { baseLayerType : dojox.geo.openlayers.BaseLayerType.Transport } );
//      		map = new Map("map", { baseLayerType : dojox.geo.openlayers.BaseLayerType.GOOGLE } );
		map.fitTo([ -3, 55, 3, 48]);

		map.olMap.events.register( "click", map.olMap, function (e) {
                        var ll = map.olMap.getLonLatFromPixel( e.xy);
		        ll.transform(  map.olMap.getProjectionObject(), "EPSG:4326" );
			console.log( "clicked", e.xy, ll.lat, ll.lon );
		    });

		map.olMap.addControl(new OpenLayers.Control.PanZoomBar());
		// map.olMap.addControl(new OpenLayers.Control.LayerSwitcher({'ascending':false}));
		map.olMap.addControl(new OpenLayers.Control.Permalink());
		map.olMap.addControl(new OpenLayers.Control.Permalink('permalink'));
		map.olMap.addControl(new OpenLayers.Control.MousePosition());

		var train_data = new jrest({
			          target: "./train_.php/vwTrainLocsO/"
		});
                //var stanox_data = new jrest({
		//      target: "http://hearnden.org.uk/traintrack/train_.php/stanoxTostanox/"
		//});
		//var a = new Array();
		//a[0] = { stanox1: '34512', stanox2: '12890' };
		//a[1] = { stanox1: '1234', stanox2: '1231' };
		//var tv = stanox_data.put(a  ).then( function( r ){
		//    console.log( r );
		//    });
		var layer, oldLayer;
		var f;
		var p;
		var featureMap = new Object;
		var t= new timething.Timer( 30000);
		var layer = new GfxLayer();
		var features = Array();
		var wayPoints = Array();

		map.addLayer( layer );
		function RenderTrainResults( item ) {
		    var oldFeatures = new Array();

		    //http://dojotoolkit.org/reference-guide/1.9/dojox/geo/openlayers.html#id6
		    //		oldLayer = layer;
		    //    		layer = new GfxLayer();
		    //		layer.clear();
		    for( fm in featureMap ){
			featureMap[fm].found = false;
		    }
		    for( i =0; i < item.length; i++ ){
			//len = toMinutes( item[i].station2_tme ) - toMinutes( item[i].station1_tme);
			//if( len < 0 ) {
			//    len = len + 24 * 60;
			//}
			//len = len * 60; // seconds.
			//var nw = Date.now() / 1000;
			//elapsed = nw - parseInt(item[i].whenTime );
			//var proportion = 0.0;
			//if ( elapsed > len ){
			//    proportion = 1.0;
			//} else {
			//    proportion = elapsed / len;
			//}

			var lng = Number( item[i].long_ );
			var lat = Number( item[i].Lat );
			var addFeature = true;
			if( item[i].id in featureMap  ){
			    var x = featureMap[ item[i].id ];
			    x.found = true;
			    if( x.lat == lat && x.lng == lng ){
				addFeature = false;
			    }
			    else {
				if( x.found == false ){
				    oldFeatures[ oldFeatures.length ] = x.feature;
				    if( "mouseover" in x ){
					x.mouseover.remove();
				    }
				    if( "mouseout" in x ){
					x.mouseout.remove();
				    }
				}
				else {
				    addFeature = false; // 2 locations for a single stanox.
				}
			    }
			}		
			if( addFeature == true ){
			    p = new dojox.geo.openlayers.Point({x : lng, y: lat } );
			    // create a GeometryFeature
			    f = new GeometryFeature(p);
			    var toc_id = item[i].toc_id;
			    var toc = toc_id;
			    // set the shape properties, fill and stroke
			    if (toc_id == 06){
				f.setFill([ 225, 225, 0]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    }
			    if (toc_id == 20){
				f.setFill([ 51, 0 , 225 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    }
			    if (toc_id == 21){
				f.setFill([ 255, 0 , 0 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    }
			    else if (toc_id == 22){
				f.setFill([ 0,0,0 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 23){
				f.setFill([ 153, 0, 153 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 24){
				f.setFill([ 255, 102, 0]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 25){
				f.setFill([ 51, 0 , 153 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 26){
				f.setFill([ 51, 204, 204 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					});
			    } else if (toc_id == 27){
				f.setFill([ 204, 204, 204]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 28){
				f.setFill([ 255,204,51 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 29){
				f.setFill([ 102,153,0 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 30){
				f.setFill([ 255, 153, 0 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 34){
				f.setFill([ 0, 51, 0 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 50){
				f.setFill([ 102,0,0 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 51){
				f.setFill([ 0,0,0]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 55){
				f.setFill([  51,153,255]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 56){
				f.setFill([ 0,0,204 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 60){
				f.setFill([ 102, 51, 153 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 61){
				f.setFill([ 102,102,102 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 64){
				f.setFill([ 255,255, 0 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 65){
				f.setFill([ 153, 0, 0 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 71){
				f.setFill([ 102,204,153 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 74){
				f.setFill([ 225-parseInt(toc)*2, 225-parseInt(toc)*2, 225 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 79){
				f.setFill([ 225-parseInt(toc)*2, 225-parseInt(toc)*2, 225 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 80){
				f.setFill([ 225-parseInt(toc)*2, 225-parseInt(toc)*2, 225 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 81){
				f.setFill([ 225-parseInt(toc)*2, 225-parseInt(toc)*2, 225 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					});
			    } else if (toc_id == 82){
				f.setFill([ 225-parseInt(toc)*2, 225-parseInt(toc)*2, 225 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 84){
				f.setFill([ 225-parseInt(toc)*2, 225-parseInt(toc)*2, 225 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 85){
				f.setFill([ 225-parseInt(toc)*2, 225-parseInt(toc)*2, 225 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 86){
				f.setFill([ 225-parseInt(toc)*2, 225-parseInt(toc)*2, 225 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 90){
				f.setFill([ 225-parseInt(toc)*2, 225-parseInt(toc)*2, 225 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 91){
				f.setFill([ 225-parseInt(toc)*2, 225-parseInt(toc)*2, 225 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else if (toc_id == 93){
				f.setFill([ 225-parseInt(toc)*2, 225-parseInt(toc)*2, 225 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    } else{
				f.setFill([ 225-parseInt(toc)*2, 225-parseInt(toc)*2, 225 ]);
				f.setStroke([ 0, 0, 0 ]);
				f.setShapeProperties({
					r : 5
					    });
			    }
			    featureMap[ item[i].id ] = { feature : f, lat : lat, lng : lng, found : true };
			    layer.addFeature(f);

			}

			
		    }
		    var removes = new Array();
		    for( fm in featureMap ){
			if( featureMap[fm].found == false ){
			    oldFeatures[oldFeatures.length] = featureMap[fm].feature;
			    removes[ removes.length ] = fm;
			    if( "mouseover" in featureMap[fm] ){
				featureMap[fm].mouseover.remove();
			    }
			    if( "mouseout" in featureMap[fm] ){
				featureMap[fm].mouseout.remove();
			    }

			}
		    }
		    // remove old  features from the layer
		    layer.removeFeature( oldFeatures );
		    for( i = 0; i < removes.length ;i++ ){
			delete featureMap[ removes[i] ];
		    }

		    layer.redraw();
		    // Now we wire up an assign with data
		    for( i = 0; i < item.length; i++ ){
			bound( item[i], featureMap[item[i].id] );
		    }
		}
		t.onTick = function(){
		    train_data.query( {} ).then(function(item){
				RenderTrainResults( item );
			    });
		}

		train_data.query( {} ).then(function(item){
			RenderTrainResults( item );
		    });
    	t.start();

	});
});
