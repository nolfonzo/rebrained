class Element:
    def __init__(self,value,next=None):
        self.value=value
        self.next=next

def findMToLast(element,m,counter=1):
    """
    >>> e1=Element(1)
    >>> e2=Element(2)
    >>> e3=Element(3)
    >>> e4=Element(4)
    >>> e1.next=e2
    >>> e2.next=e3
    >>> e3.next=e4
    >>> findMToLast(e1,1).value
    4
    >>> findMToLast(e1,2).value
    3
    >>> findMToLast(e1,3).value
    2
    >>> findMToLast(e1,4).value
    1
    """
    if element.next==None:
        if m==1: mtolast=element
        else: mtolast=None
        if counter==1: return mtolast
        return (counter,mtolast)
    (size,mtolast)=findMToLast(element.next,m,counter+1)
    if size-counter+1==m: mtolast=element
    if counter==1: return mtolast
    return (size,mtolast)

if __name__ == "__main__":
    import doctest
    doctest.testmod()
