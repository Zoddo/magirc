$.fn.dataTableExt.oApi.fnReloadAjax=function(a,b,c,d){typeof b!="undefined"&&b!=null&&(a.sAjaxSource=b),this.oApi._fnProcessingDisplay(a,!0);var e=this,f=a._iDisplayStart;a.fnServerData(a.sAjaxSource,[],function(b){e.oApi._fnClearTable(a);for(var g=0;g<b.aaData.length;g++)e.oApi._fnAddData(a,b.aaData[g]);a.aiDisplay=a.aiDisplayMaster.slice(),e.fnDraw(),typeof d!="undefined"&&d===!0&&(a._iDisplayStart=f,e.fnDraw(!1)),e.oApi._fnProcessingDisplay(a,!1),typeof c=="function"&&c!=null&&c(a)},a)}