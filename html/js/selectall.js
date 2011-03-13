function selectall() {
    var trk=0;
    frm = document.forms['songs']
        for (var i=0;i<frm.elements.length;i++)
        {
            var e = frm.elements[i];
            if ((e.name != 'allsongs') && (e.type=='checkbox'))
            {
                trk++;
                e.checked = frm.allsongs.checked;
            }
        }
}

