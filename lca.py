def commonAncestor(node1,node2,head):
    """
    >>> node1=Node(1)
    >>> node4=Node(4)
    >>> node3=Node(3,node1,node4)
    >>> node7=Node(7)
    >>> node5=Node(5,node3,node7)
    >>> commonAncestor(node1,node7,node5).value
    5
    >>> commonAncestor(node1,node5,node5).value
    5
    >>> commonAncestor(node1,node4,node5).value
    3
    """
    if head==None: return
    if (node1.value <= head.value) & (node2.value >= head.value):
        return head
    if (node1.value <= head.value) & (node2.value <= head.value): 
        return commonAncestor(node1,node2,head.left) 
    if (node1.value >= head.value) & (node2.value >=head.value):
        return commonAncestor(node1,node2,head.right)

class Node:
    def __init__(self,value,left=None,right=None):
        self.value=value
        self.left=left
        self.right=right

if __name__ == "__main__":
    print "hello"
    import doctest
    doctest.testmod()

